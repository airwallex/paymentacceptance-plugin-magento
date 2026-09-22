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
 * Emits each funnel stage straight from the browser to the Airwallex airtracker
 * ingestion endpoint — the same pipeline the checkout SDK already uses for its
 * `source=magento` client events. The `/airtracker/logs` route is unauthenticated
 * and a `text/plain` beacon is a CORS "simple request" (no preflight, no token),
 * so no server round-trip is needed. Every event carries a random `session_id`
 * in `commonData` so a single checkout attempt (rendered -> selected -> submitted
 * -> succeeded / failed) can be stitched downstream. Counts only — no amounts, no PII.
 *
 * The id lives only in memory: it is created when the checkout context first
 * emits an event and rotated whenever a new attempt begins (see {@link newSession},
 * called on express-checkout render). A page refresh starts a fresh JS context and
 * therefore a brand-new id, so each page load / express render is its own attempt.
 *
 * Telemetry is fire-and-forget: every public call swallows its own errors so it
 * can never interfere with checkout.
 */
/** The `data[]` log entry sent to airtracker. */
/** Options accepted by {@link track}. */
/** Persisted shape of a durable id in `localStorage`. */
define([], function () {
    'use strict';
    /** Airtracker ingestion endpoints (unauthenticated `/logs` route). */
    var PROD_ENDPOINT = 'https://o11y.airwallex.com/airtracker/logs';
    var SANDBOX_ENDPOINT = 'https://o11y.sandbox.airwallex.com/airtracker/logs';
    /** Registered airtracker app name shared by the PA plugins; magento is the source. */
    var APP_NAME = 'pa_plugin';
    var SOURCE = 'magento';
    var DEVICE_KEY = 'awx_checkout_device_id';
    /** Funnel stage event names (SCREAMING_SNAKE, per spec convention). */
    var EVENTS = {
        RENDERED: 'CHECKOUT_ELEMENT_RENDERED',
        METHOD_SELECTED: 'PAYMENT_METHOD_SELECTED',
        SUBMITTED: 'PAYMENT_SUBMITTED',
        SUCCEEDED: 'CHECKOUT_SUCCEEDED',
        FAILED: 'CHECKOUT_FAILED'
    };
    /** The current in-memory tracking id (rotated per page load / express render). */
    var currentSessionId                = null;
    /** In-page fallback device id when localStorage is unavailable (private mode etc.). */
    var memoryDeviceId                = null;
    /**
     * Whether a method-selected event has already fired on this page load. The
     * first selection on the page corresponds to the default (pre-selected)
     * payment method, which is what EPP-1214 asks us to capture in addition to
     * explicit changes.
     */
    var methodSelectedOnce = false;
    function nowMs()         {
        return (new Date()).getTime();
    }
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
    function readStore(key        )                       {
        try {
            var raw = window.localStorage.getItem(key);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw)                 ;
            if (parsed && typeof parsed.id === 'string' && typeof parsed.ts === 'number') {
                return parsed;
            }
        } catch (e) {
            // localStorage unreadable / blocked — treat as absent
        }
        return null;
    }
    function writeStore(key        , id        , ts        )       {
        try {
            window.localStorage.setItem(key, JSON.stringify({ id: id, ts: ts }));
        } catch (e) {
            // localStorage unwritable — the in-memory id keeps the page stitched
        }
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
     * Start a brand-new tracking id for a fresh checkout attempt. Called when the
     * express checkout (re)renders so each express render — and each retry after a
     * failure — is stitched under its own id.
     */
    function newSession()         {
        currentSessionId = uuid();
        methodSelectedOnce = false;
        return currentSessionId;
    }
    /** Return a device id that persists across sessions (no sliding expiry). */
    function getDeviceId()         {
        var stored = readStore(DEVICE_KEY);
        if (stored) {
            memoryDeviceId = stored.id;
            return stored.id;
        }
        var id = memoryDeviceId || uuid();
        memoryDeviceId = id;
        writeStore(DEVICE_KEY, id, nowMs());
        return id;
    }
    /** The plugin config block, read defensively (present on checkout pages). */
    function paymentConfig()                          {
        try {
            var cfg = window.checkoutConfig;
            if (cfg && cfg.payment && cfg.payment.airwallex_payments) {
                return cfg.payment.airwallex_payments                           ;
            }
        } catch (e) {
            // no config on this page
        }
        return {};
    }
    /** Map the plugin mode to a valid airtracker environment (prod | demo). */
    function getEnv()         {
        return paymentConfig().mode === 'prod' ? 'prod' : 'demo';
    }
    function getAppVersion()         {
        var version = paymentConfig().plugin_version;
        return version ? String(version) : 'unknown';
    }
    function endpointUrl()         {
        return getEnv() === 'prod' ? PROD_ENDPOINT : SANDBOX_ENDPOINT;
    }
    function buildEnvelope(eventName        , opts              )                          {
        var log            = { severity: 'info', eventName: eventName };
        if (opts.payment_method) {
            log.payment_method = String(opts.payment_method);
        }
        if (opts.is_default) {
            log.is_default = true;
        }
        if (opts.error_reason) {
            log.error_reason = String(opts.error_reason).slice(0, 255);
        }
        return {
            commonData: {
                appName: APP_NAME,
                source: SOURCE,
                deviceId: getDeviceId(),
                sessionId: getSessionId(),
                appVersion: getAppVersion(),
                env: getEnv()
            },
            data: [log]
        };
    }
    /**
     * Send a single funnel event straight to airtracker. Fire-and-forget: prefers
     * `sendBeacon` with a `text/plain` body (a CORS simple request, so no preflight
     * and no auth — and it survives the navigation that follows submit/success),
     * falling back to a keep-alive no-cors `fetch`. Never throws.
     */
    function track(eventName        , opts               )       {
        try {
            var body = JSON.stringify(buildEnvelope(eventName, opts || {}));
            var url = endpointUrl();
            if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
                var blob = new Blob([body], { type: 'text/plain' });
                if (navigator.sendBeacon(url, blob)) {
                    return;
                }
            }
            if (typeof window.fetch === 'function') {
                window.fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'text/plain' },
                    body: body,
                    keepalive: true,
                    mode: 'no-cors'
                }).catch(function () {
                    // telemetry failures must stay silent
                });
            }
        } catch (e) {
            // Never let telemetry break the checkout flow.
        }
    }
    /**
     * Track a payment-method selection, flagging the first selection on the page
     * as the default (pre-selected) method.
     */
    function trackMethodSelected(paymentMethod        )       {
        var isDefault = !methodSelectedOnce;
        methodSelectedOnce = true;
        track(EVENTS.METHOD_SELECTED, { payment_method: paymentMethod, is_default: isDefault });
    }
    return {
        EVENTS: EVENTS,
        getSessionId: getSessionId,
        newSession: newSession,
        getDeviceId: getDeviceId,
        track: track,
        trackMethodSelected: trackMethodSelected
    };
});
