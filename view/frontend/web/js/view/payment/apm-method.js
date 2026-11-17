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
/* global Airwallex */
/** `this` receiver for the APM renderer's methods. */
/** Checkout funnel telemetry module (EPP-1214). */
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'ko',
    'mage/url',
    'mage/storage',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/translate',
    'Airwallex_Payments/js/view/payment/tracking'
], function (
    Component                        ,
    $              ,
    ko                ,
    urlBuilder            ,
    storage             ,
    utils                ,
    additionalValidators                      ,
    addressHandler                      ,
    quote            ,
    customerData         ,
    redirectOnSuccessAction                         ,
    $t             ,
    tracking                
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Airwallex_Payments/payment/apm-method',
            code: 'airwallex_payments_apm',
            timer: null,
        },
        apmElement: null,
        // Serialize initialization and discard work invalidated by checkout changes.
        isApmInitializing: false,
        isSubmitting: false,
        isDisposed: false,
        apmGeneration: 0,
        isApmElementMounted: ko.observable(false),
        isApmContainerReady: ko.observable(false),
        agreementsChecked: ko.observable(false),
        lastBillingAddress: null,
        lastGrandTotal: null,
        agreementsBound: false,
        currentCurrency: null,
        selectedCurrency: null,
        currencyToken: 0,
        initialConversionRate: null,
        preQuoteFailed: false,
        getCode(                 ) {
            return this.code;
        },
        getPaymentConfig() {
            if (window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.airwallex_payments) {
                return window.checkoutConfig.payment.airwallex_payments;
            }
            return {};
        },
        shouldShowLogos(                 ) {
            return this.getPaymentMethodLogos().length > 0;
        },
        getPaymentMethodLogos(                 ) {
            const paymentConfig = this.getPaymentConfig();
            return paymentConfig.apm_selected_logos || [];
        },
        isPaymentBeforeOrder(                 ) {
            const paymentConfig = this.getPaymentConfig();
            return paymentConfig.is_order_before_payment === false;
        },
        isAgreementsEnabled() {
            const config = window.checkoutConfig && window.checkoutConfig.checkoutAgreements;
            return !!(config && config.isEnabled && config.agreements && config.agreements.length > 0);
        },
        hasAgreements(                 ) {
            return this.getAgreementCheckboxes().length > 0;
        },
        getAgreementCheckboxes() {
            return $('.payment-method._active div[data-role=checkout-agreements] input[type="checkbox"]');
        },
        hasBillingAddress() {
            const billing = quote.billingAddress();
            return !!(billing && billing.countryId);
        },
        getBillingCurrency() {
            const billing = quote.billingAddress();
            if (!billing || !billing.countryId) {
                return '';
            }
            const map = window.checkoutConfig?.payment?.airwallex_payments?.country_to_currency || {};
            return map[String(billing.countryId).toUpperCase()] || '';
        },
        canMountApmElement(                 ) {
            if (this.isDisposed || this.isChecked() !== this.getCode() || !this.isPaymentBeforeOrder()) {
                return false;
            }
            if (!this.hasBillingAddress()) {
                return false;
            }
            if (this.isAgreementsEnabled() && !this.areAllAgreementsChecked()) {
                return false;
            }
            return true;
        },
        onApmContainerRendered(                 ) {
            this.watchAgreementChanges();
            this.tryMountApmElement();
        },
        tryMountApmElement(                 ) {
            if (!this.canMountApmElement()) {
                if (this.isApmElementMounted() || this.isApmInitializing) {
                    this.unmountApmElement();
                }
                return;
            }
            if (!this.isApmElementMounted()) {
                this.mountApmElement();
            }
        },
        initialize(                 ) {
            this._super();
            this.checkoutSubscriptions = [];
            this.lastBillingAddress = ko.toJS(quote.billingAddress());
            this.lastGrandTotal = quote.totals()?.base_grand_total ?? null;
            this.checkoutSubscriptions.push(this.isChecked.subscribe(this.onPaymentMethodChange.bind(this)));
            this.checkoutSubscriptions.push(quote.billingAddress.subscribe(function (                   newAddress                              ) {
                if (utils.isSameBillingAddress(this.lastBillingAddress, newAddress)) {
                    return;
                }
                this.lastBillingAddress = ko.toJS(newAddress);
                this.selectedCurrency = null;
                if (this.isChecked() !== this.getCode()) return;
                utils.hideYouPay();
                this.cleanupApmElement();
                if (this.isChecked() === this.getCode() && this.isPaymentBeforeOrder()) {
                    this.initPaymentBeforeOrderFlow();
                }
            }.bind(this)));
            this.checkoutSubscriptions.push(quote.totals.subscribe(function (                   newTotals     ) {
                if (this.isChecked() !== this.getCode() || !newTotals) {
                    return;
                }
                let newGrandTotal = newTotals.base_grand_total;
                if (this.lastGrandTotal !== null && this.lastGrandTotal !== newGrandTotal) {
                    this.cleanupApmElement();
                    if (this.isChecked() === this.getCode() && this.isPaymentBeforeOrder()) {
                        this.initPaymentBeforeOrderFlow();
                    }
                }
                this.lastGrandTotal = newGrandTotal;
            }.bind(this)));
            this.checkoutSubscriptions.push(this.isApmContainerReady.subscribe(function (                   isReady         ) {
                if (isReady && this.isChecked() === this.getCode() && !this.isApmElementMounted()) {
                    ko.tasks.schedule(() => {
                        this.tryMountApmElement();
                    });
                }
            }.bind(this)));
            this.checkoutSubscriptions.push(this.isApmElementMounted.subscribe(function (                   mounted         ) {
                if (!mounted) {
                    utils.removeCheckoutCurrencySwitcher();
                    utils.hideYouPay();
                }
            }.bind(this)));
            this.onPaymentMethodChange(this.isChecked());
            const intentId = utils.getQueryParam('intent_id');
            if (intentId && !window['awx_handling_intent_' + intentId]) {
                window['awx_handling_intent_' + intentId] = true;
                this.watchPaymentConfirmation(intentId, utils.getQueryParam('state'));
            }
            return this;
        },
        destroy(                 ) {
            this.isDisposed = true;
            this.checkoutSubscriptions?.forEach((subscription                      ) => subscription.dispose());
            if (this.agreementChangeHandler) $(document).off('change.apm', this.agreementChangeHandler);
            this.cleanupApmElement();
            return this._super();
        },
        watchPaymentConfirmation(                   intentId        , state                ) {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            this.timer = utils.watchPaymentConfirmation(intentId, (intentResponse) => {
                this.timer = null;
                utils.clearDataAfterPay(intentResponse, customerData);
                redirectOnSuccessAction.execute();
            }, (error) => {
                console.error('Error while polling APM payment intent:', error);
            }, state || undefined);
        },
        onPaymentMethodChange(                   selectedMethod               ) {
            if (selectedMethod === this.getCode()) {
                if (this.isPaymentBeforeOrder()) {
                    this.initPaymentBeforeOrderFlow();
                } else {
                    tracking.trackRendered('apm');
                    tracking.trackMethodSelected('apm');
                }
            } else if (this.isApmContainerReady() || this.isApmInitializing || this.apmElement) {
                this.cleanupApmElement();
            }
        },
        unmountApmElement(                 ) {
            ++this.apmGeneration;
            ++this.currencyToken;
            if (this.apmElement) {
                try {
                    this.apmElement.destroy();
                } catch (e) {
                    console.error('Error destroying APM element:', e);
                }
                this.apmElement = null;
            }
            this.isApmElementMounted(false);
        },
        cleanupApmElement(                 ) {
            this.unmountApmElement();
            this.isApmContainerReady(false);
            this.currentCurrency = null;
            this.initialConversionRate = null;
            this.preQuoteFailed = false;
            utils.removeCheckoutCurrencySwitcher();
            utils.hideYouPay();
        },
        selectPaymentMethod(                 ) {
            this._super();
            if (this.isPaymentBeforeOrder()) {
                this.initPaymentBeforeOrderFlow();
            }
            return true;
        },
        initPaymentBeforeOrderFlow(                 ) {
            this.isApmContainerReady(true);
        },
        watchAgreementChanges(                 ) {
            if (this.agreementsBound) {
                return;
            }
            this.agreementsBound = true;
            const self = this;
            this.agreementChangeHandler = function(                 ) {
                const $paymentMethod = $(this).closest('.payment-method');
                const isApmMethod = $paymentMethod.hasClass(self.getCode());
                if (!isApmMethod || self.isChecked() !== self.getCode()) {
                    return;
                }
                self.agreementsChecked(self.areAllAgreementsChecked());
                self.tryMountApmElement();
            };
            $(document).off('change.apm').on('change.apm', 'div[data-role=checkout-agreements] input[type="checkbox"]', this.agreementChangeHandler);
        },
        areAllAgreementsChecked(                 ) {
            const $agreements = this.getAgreementCheckboxes();
            if ($agreements.length === 0) {
                return !this.isAgreementsEnabled();
            }
            let allChecked = true;
            $agreements.each(function(                 ) {
                if (!$(this).prop('checked')) {
                    allChecked = false;
                    return false;
                }
            });
            return allChecked;
        },
        async mountApmElement(                 ) {
            if (!this.canMountApmElement() || !document.getElementById('airwallex-apm-element-checkout') || this.isApmElementMounted() || this.isApmInitializing) {
                return;
            }
            this.isApmInitializing = true;
            const generation = this.apmGeneration;
            const $body = $('body');
            $body.trigger('processStart');
            $('#airwallex-apm-error').hide().empty();
            try {
                await this.initializeAirwallex();
                if (generation === this.apmGeneration && this.canMountApmElement() && this.apmElement) {
                    this.isApmElementMounted(true);
                }
            } catch (e     ) {
                if (generation !== this.apmGeneration || !this.canMountApmElement()) return;
                this.cleanupApmElement();
                let msg = $t('Failed to initialize payment system. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                } else if (e && e.message) {
                    msg = e.message;
                }
                this.showError(msg);
            } finally {
                this.isApmInitializing = false;
                $body.trigger('processStop');
                if (generation !== this.apmGeneration && this.isApmContainerReady()) {
                    this.tryMountApmElement();
                }
            }
        },
        async initializeAirwallex(                 ) {
            const generation = this.apmGeneration;
            tracking.newSession();
            const intentResponse = await this.createPaymentIntent();
            if (!intentResponse || generation !== this.apmGeneration || !this.canMountApmElement()) return;
            if (utils.completeRecoveredOrder(intentResponse)) return;
            this.returnState = intentResponse.state || '';
            if (!intentResponse.element_options) {
                throw new Error('Missing element options from server');
            }
            let elementOptions = intentResponse.element_options;
            if (typeof elementOptions === 'string') {
                elementOptions = JSON.parse(elementOptions);
            }
            await this.applyBillingCurrencyToElementOptions(elementOptions);
            if (generation !== this.apmGeneration || !this.canMountApmElement()) return;
            const paymentConfig = this.getPaymentConfig();
            // components-sdk only accepts 'demo' for sandbox; map accordingly.
            const env = (paymentConfig.mode === 'sandbox' || paymentConfig.mode === 'demo') ? 'demo' : 'prod';
            Airwallex.init({
                env: env,
                origin: window.location.origin,
            });
            elementOptions.disableAutoCurrencyConversion = true;
            await this.createApmElement(elementOptions);
        },
        async applyBillingCurrencyToElementOptions(                   elementOptions     ) {
            this.preQuoteFailed = false;
            this.initialConversionRate = null;
            const baseCurrency = elementOptions.currency ? (this.getPaymentConfig().quote_currency_code || elementOptions.currency).toUpperCase() : '';
            if (!baseCurrency) return;
            const currencies = utils.buildSwitcherCurrencies(baseCurrency, this.getPaymentConfig().available_currencies);
            if (this.selectedCurrency && currencies.indexOf(this.selectedCurrency) === -1) this.selectedCurrency = null;
            const targetCurrency = this.selectedCurrency || (this.getBillingCurrency() || '').toUpperCase();
            elementOptions.currency = baseCurrency;
            elementOptions.quote_id = undefined;
            this.currentCurrency = baseCurrency;
            if (!targetCurrency || !baseCurrency || targetCurrency === baseCurrency) return;
            try {
                const quoteResp = await utils.conversionQuote(baseCurrency, targetCurrency);
                elementOptions.currency = targetCurrency;
                elementOptions.quote_id = quoteResp.id;
                this.currentCurrency = targetCurrency;
                this.initialConversionRate = quoteResp.conversion_rate;
            } catch (e) {
                // Never silently replace an explicit shopper choice after a failed refresh.
                if (this.selectedCurrency) throw e;
                this.currentCurrency = null;
                this.initialConversionRate = null;
                this.preQuoteFailed = true;
            }
        },
        async createPaymentIntent(                 ) {
            const generation = this.apmGeneration;
            const payload                       = {
                cartId: quote.getQuoteId(),
                paymentMethod: {
                    method: this.code,
                    additional_data: {},
                    extension_attributes: {
                        'agreement_ids': utils.getAgreementIds()
                    }
                },
            };
            await utils.setRecaptchaToken(payload, utils.getRecaptchaId());
            if (generation !== this.apmGeneration || !this.canMountApmElement()) return null;
            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }
            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());
            if (generation !== this.apmGeneration || !this.canMountApmElement()) return null;
            return await utils.getIntent(payload, {});
        },
        async createApmElement(                   elementOptions     ) {
            const self = this;
            this.apmElement = Airwallex.createElement('dropIn', elementOptions);
            const element = this.apmElement;
            const isCurrent = () => self.apmElement === element && self.canMountApmElement();
            let redirectUrl = urlBuilder.build('airwallex/redirect?type=quote&id=' + quote.getQuoteId());
            if (this.returnState) redirectUrl += '&state=' + encodeURIComponent(this.returnState);
            const completePayment = () => {
                if (self.apmElement !== element) return;
                $('body').trigger('processStart');
                utils.clearDataAfterPay({}, customerData);
                window.location.href = redirectUrl;
            };
            // The CDN SDK attaches .on() listeners to the DOM node assigned by mount().
            element .mount('airwallex-apm-element-checkout');
            this.apmElement .on('ready', function() {
                if (!isCurrent()) return;
                tracking.trackRendered('apm', tracking.getSessionId());
                tracking.trackMethodSelected('apm');
            });
            this.apmElement .on('success', completePayment);
            this.apmElement .on('error', function(event                                                      ) {
                if (self.apmElement !== element) return;
                if (event?.detail?.error?.code === 'invalid_status_for_operation') {
                    // The return controller retrieves the intent and verifies payment before completing the order.
                    completePayment();
                    return;
                }
                if (!isCurrent()) return;
                tracking.useSession('apm');
                tracking.track(tracking.EVENTS.FAILED, { payment_method: 'apm' });
            });
            this.apmElement .on('quoteCreate', function(event) {
                if (!isCurrent()) return;
                const quote = event?.detail?.quote;
                if (quote) {
                    utils.showYouPay(quote, $t);
                } else {
                    utils.hideYouPay();
                }
            });
            await this.maybeRenderCurrencySwitcher();
        },
        async maybeRenderCurrencySwitcher(                 ) {
            const generation = this.apmGeneration;
            const token = this.currencyToken;
            const quoteCurrency = (this.getPaymentConfig().quote_currency_code || '').toUpperCase();
            const billingCurrency = (this.getBillingCurrency() || '').toUpperCase();
            if (!billingCurrency || (billingCurrency === quoteCurrency && (!this.currentCurrency || this.currentCurrency === quoteCurrency))) {
                utils.removeCheckoutCurrencySwitcher();
                return;
            }
            // Pre-quote failed — don't show switcher, use order currency instead
            if (this.preQuoteFailed) {
                utils.removeCheckoutCurrencySwitcher();
                utils.hideYouPay();
                return;
            }
            this.renderCurrencySwitcher();
            if (this.initialConversionRate) {
                try {
                    const grandTotal = await this.fetchGrandTotal();
                    if (generation !== this.apmGeneration || token !== this.currencyToken || !this.canMountApmElement()) return;
                    const rate = Number(this.initialConversionRate);
                    utils.showYouPay({
                        target_amount: grandTotal * rate,
                        target_currency: this.currentCurrency || billingCurrency,
                        payment_currency: quoteCurrency,
                        client_rate: this.initialConversionRate,
                    }, $t);
                } catch (e) {
                    if (generation === this.apmGeneration && token === this.currencyToken && this.canMountApmElement()) utils.hideYouPay();
                }
            }
        },
        async fetchGrandTotal() {
            const url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
            const resp = await storage.get(url, undefined, 'application/json', {});
            const expressData = typeof resp === 'string' ? JSON.parse(resp) : resp;
            return Number(expressData.grand_total);
        },
        renderCurrencySwitcher(                 ) {
            const paymentConfig = this.getPaymentConfig();
            const quoteCurrency = (paymentConfig.quote_currency_code || '').toUpperCase();
            const currencies = utils.buildSwitcherCurrencies(quoteCurrency, paymentConfig.available_currencies);
            if (currencies.length === 0) {
                utils.removeCheckoutCurrencySwitcher();
                return;
            }
            if (!this.currentCurrency) {
                this.currentCurrency = quoteCurrency;
            }
            const billing = quote.billingAddress();
            const billingCountryCode = billing ? billing.countryId : undefined;
            const self = this;
            utils.renderCheckoutCurrencySwitcher(
                currencies,
                this.currentCurrency,
                billingCountryCode,
                function (selected        ) {
                    self.onCurrencyChange(selected, quoteCurrency);
                },
                $t
            );
        },
        async onCurrencyChange(                   selected        , baseCurrency        ) {
            const element = this.apmElement;
            if (!element || !this.canMountApmElement()) return;
            const token = ++this.currencyToken;
            const effectiveCurrent = this.currentCurrency || baseCurrency;
            if (selected === effectiveCurrent) {
                this.selectedCurrency = selected;
                return;
            }
            try {
                const quoteResp = selected === baseCurrency ? null : await utils.conversionQuote(baseCurrency, selected);
                if (token !== this.currencyToken || element !== this.apmElement || !this.canMountApmElement()) return;
                element.update({ currency: selected, quote_id: quoteResp?.id });
                this.currentCurrency = selected;
                this.selectedCurrency = selected;
                utils.hideYouPay();
                if (!quoteResp) return;
                const grandTotal = await this.fetchGrandTotal();
                if (token !== this.currencyToken || element !== this.apmElement || !this.canMountApmElement()) return;
                utils.showYouPay({
                    target_amount: grandTotal * Number(quoteResp.conversion_rate),
                    target_currency: selected,
                    payment_currency: baseCurrency,
                    client_rate: quoteResp.conversion_rate,
                }, $t);
            } catch (e     ) {
                if (token !== this.currencyToken || element !== this.apmElement || !this.canMountApmElement()) return;
                utils.setSelectedCurrency('#airwallex-apm-currency-switcher-checkout', this.currentCurrency || baseCurrency);
                this.showError(e?.responseJSON?.message || e?.message || $t('Failed to convert currency. Please try again.'));
            }
        },
        async placeOrder(                   _data          , event        ) {
            if (event) {
                event.preventDefault();
            }
            if (this.isDisposed || this.isSubmitting || this.isChecked() !== this.getCode()) return false;
            if (this.isPaymentBeforeOrder()) {
                this.showError($t('Please complete payment using the payment method above.'));
                return false;
            }
            if (!this.validate() || !additionalValidators.validate()) {
                return false;
            }
            this.isSubmitting = true;
            const $body = $('body');
            $body.trigger('processStart');
            tracking.useSession('apm');
            tracking.track(tracking.EVENTS.SUBMITTED, { payment_method: 'apm' });
            try {
                if (await this._placeOrder() === false) $body.trigger('processStop');
            } catch (e     ) {
                let msg = $t('Something went wrong while processing your request. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                } else if (e && e.message) {
                    msg = e.message;
                } else if (typeof e === 'string') {
                    msg = e;
                }
                tracking.useSession('apm');
                tracking.track(tracking.EVENTS.FAILED, { payment_method: 'apm', error_reason: msg });
                this.showError(msg);
                $body.trigger('processStop');
            } finally {
                this.isSubmitting = false;
            }
            return true;
        },
        async _placeOrder(                 ) {
            const payload                       = {
                cartId: quote.getQuoteId(),
                paymentMethod: {
                    method: this.code,
                    additional_data: {},
                    extension_attributes: {
                        'agreement_ids': utils.getAgreementIds()
                    }
                },
            };
            await utils.setRecaptchaToken(payload, utils.getRecaptchaId());
            if (this.isDisposed || this.isChecked() !== this.getCode()) return false;
            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }
            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());
            if (this.isDisposed || this.isChecked() !== this.getCode()) return false;
            const intentResponse = await utils.getIntent(payload, {});
            if (utils.completeRecoveredOrder(intentResponse)) return;
            if (this.isDisposed || this.isChecked() !== this.getCode()) return false;
            let apmUrl = urlBuilder.build('airwallex/apm/index');
            if (intentResponse.order_id) {
                apmUrl += '?order_id=' + intentResponse.order_id
                    + '&intent_id=' + encodeURIComponent(intentResponse.intent_id)
                    + '&state=' + encodeURIComponent(intentResponse.state || '');
            } else {
                apmUrl += '?quote_id=' + quote.getQuoteId()
                    + '&state=' + encodeURIComponent(intentResponse.state || '');
            }
            apmUrl += '&awx_session_id=' + encodeURIComponent(tracking.getSessionId());
            utils.clearDataAfterPay({}, customerData);
            location.href = apmUrl;
        },
        showError(message        ) {
            const $errorContainer = $('#airwallex-apm-error');
            if (!$errorContainer.length) {
                return;
            }
            $errorContainer.text(message);
            $errorContainer.show();
        },
        getData(                 ) {
            return {
                'method': this.item.method,
                'additional_data': {}
            };
        },
        disposeSubscriptions(                 ) {
            this._super();
            this.cleanupApmElement();
        }
    });
});
