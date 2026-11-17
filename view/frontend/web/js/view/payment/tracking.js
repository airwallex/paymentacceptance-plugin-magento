/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
/**
 * Client-side checkout funnel telemetry (EPP-1214, spec §4 Path A).
 *
 * The plugin logs to airtracker SERVER-SIDE via the common library, exactly like
 * every other Airwallex plugin. So this module does not talk to airtracker
 * directly; it only collects funnel stages in the browser and relays each one to
 * the plugin's own webapi endpoint (same origin — no CORS, no credentials, no
 * gateway size limits). The backend (`Service::trackCheckoutEvent`) re-emits it
 * through the common-library logger, which supplies commonData (appName, source,
 * accountId, appVersion, env, ...). Counts only — no amounts, no PII.
 *
 * Each event carries a random tracking id so one checkout attempt (rendered ->
 * selected -> submitted -> succeeded / failed) can be stitched downstream (the
 * common-library logger stamps its own per-request session id in commonData, so
 * the shopper-stitching id has to travel in the event body). The id lives only in
 * memory. Every element render starts a new id ({@link trackRendered}), including
 * a later render of the same element. Submit, confirm, and the outcome switch
 * back to that element's id ({@link useSession}) so a different element rendering
 * afterwards does not split the attempt. A page refresh starts a fresh JS context
 * and therefore brand-new ids.
 *
 * Telemetry is fire-and-forget: every public call swallows its own errors so it
 * can never interfere with checkout.
 */
/** Options accepted by {@link track}. */
define([
    'mage/url'
], function (urlBuilder            ) {
    'use strict';
    /** Same-origin plugin endpoint that relays the event to airtracker server-side. */
    var ENDPOINT = 'rest/V1/airwallex/payments/checkout-event';
    /** Funnel stage event names (SCREAMING_SNAKE, per spec convention). */
    var EVENTS = {
        RENDERED: 'CHECKOUT_ELEMENT_RENDERED',
        METHOD_SELECTED: 'PAYMENT_METHOD_SELECTED',
        SUBMITTED: 'PAYMENT_SUBMITTED',
        SUCCEEDED: 'CHECKOUT_SUCCEEDED',
        FAILED: 'CHECKOUT_FAILED'
    };
    /** The current in-memory tracking id (rotated on every element render). */
    var currentSessionId                = null;
    /** Latest tracking id for each rendered element, keyed by payment method. */
    var sessionByMethod                         = {};
    /**
     * Whether a method-selected event has already fired for the current attempt.
     * The first selection corresponds to the default (pre-selected) payment
     * method, which EPP-1214 asks us to capture in addition to explicit changes.
     */
    var methodSelectedOnce = false;
    /** RFC4122 v4 id, preferring the platform crypto implementation. */
    function uuid()         {
        try {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
        } catch (e) {
            // fall through to the manual generator
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    /**
     * Return the current tracking id, creating one on first use. Held in memory
     * only, so a page refresh (fresh JS context) yields a new id. Abandonment is
     * inferred downstream over a 30-minute window (EPP-1214, 3A): an id with a
     * rendered event but no submit is treated as abandoned — no client timer.
     */
    function getSessionId()         {
        if (!currentSessionId) {
            currentSessionId = uuid();
        }
        return currentSessionId;
    }
    /**
     * Start a brand-new tracking id. Each element render uses this so a different
     * element, or the same element rendered again, is its own attempt.
     */
    function newSession()         {
        currentSessionId = uuid();
        methodSelectedOnce = false;
        return currentSessionId;
    }
    /**
     * Open a new session for one rendered element and log CHECKOUT_ELEMENT_RENDERED
     * on it. A second render of the same method replaces that method's id.
     */
    function trackRendered(paymentMethod        , sessionId         )       {
        var existing = sessionId ? String(sessionId).trim() : '';
        var id = existing ? existing.slice(0, 64) : newSession();
        currentSessionId = id;
        var method = paymentMethod ? String(paymentMethod) : '';
        if (method) {
            sessionByMethod[method] = id;
        }
        track(EVENTS.RENDERED, { payment_method: method });
    }
    /**
     * Point later events (submit, confirm, success, failure) at the session opened
     * by the most recent render of this payment method.
     */
    function useSession(paymentMethod        )       {
        var method = paymentMethod ? String(paymentMethod) : '';
        if (method && sessionByMethod[method]) {
            currentSessionId = sessionByMethod[method];
        }
    }
    function endpointUrl()         {
        return urlBuilder.build(ENDPOINT);
    }
    /**
     * Relay a single funnel event to the plugin endpoint. Fire-and-forget: prefers
     * `sendBeacon` (survives the navigation that follows submit/success), falling
     * back to a keep-alive `fetch`. Same-origin, so credentials are fine. Never throws.
     */
    function track(eventName        , opts               )       {
        try {
            var options = opts || {};
            var payload                          = {
                eventName: eventName,
                sessionId: getSessionId()
            };
            if (options.payment_method) {
                payload.paymentMethod = String(options.payment_method);
            }
            if (options.is_default) {
                payload.isDefault = true;
            }
            if (options.error_reason) {
                payload.errorReason = String(options.error_reason).slice(0, 255);
            }
            if (options.intent_id) {
                payload.intentId = String(options.intent_id);
            }
            var url = endpointUrl();
            var body = JSON.stringify(payload);
            if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
                if (navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }))) {
                    return;
                }
            }
            if (typeof window.fetch === 'function') {
                window.fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () {
                    // telemetry failures must stay silent
                });
            }
        } catch (e) {
            // Never let telemetry break the checkout flow.
        }
    }
    /**
     * Track a payment-method selection, flagging the first selection of the
     * current attempt as the default (pre-selected) method.
     */
    function trackMethodSelected(paymentMethod        )       {
        var isDefault = !methodSelectedOnce;
        methodSelectedOnce = true;
        track(EVENTS.METHOD_SELECTED, { payment_method: paymentMethod, is_default: isDefault });
    }
    /**
     * Card and express confirm run in the browser SDK, so PHP never sees the PA
     * confirm response. Relay PAYMENT_INTENT_CONFIRMED once confirmPaymentIntent /
     * confirmIntent resolves. The backend reports it as onCheckoutFunnel.
     */
    function trackConfirm(intentId                           , paymentMethod        )       {
        if (!intentId) {
            return;
        }
        track('PAYMENT_INTENT_CONFIRMED', { payment_method: paymentMethod, intent_id: intentId });
    }
    /**
     * Keep the checkout-page tracking id on a later page (the order-before-pay
     * APM screen is a new document, so the in-memory id would otherwise reset).
     */
    function continueSession(sessionId                           )       {
        var id = sessionId ? String(sessionId).trim() : '';
        if (!id) {
            return;
        }
        currentSessionId = id.slice(0, 64);
    }
    return {
        EVENTS: EVENTS,
        getSessionId: getSessionId,
        newSession: newSession,
        trackRendered: trackRendered,
        useSession: useSession,
        continueSession: continueSession,
        track: track,
        trackMethodSelected: trackMethodSelected,
        trackConfirm: trackConfirm
    };
});
