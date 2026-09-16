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
    'mage/translate'
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
    $t             
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Airwallex_Payments/payment/apm-method',
            code: 'airwallex_payments_apm',
            timer: null,
        },
        apmElement: null,
        isApmElementMounted: ko.observable(false),
        isApmContainerReady: ko.observable(false),
        agreementsChecked: ko.observable(false),
        lastBillingAddress: null,
        lastGrandTotal: null,
        agreementsBound: false,
        currentCurrency: null,
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
            if (!this.isChecked() || !this.isPaymentBeforeOrder()) {
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
                if (this.isApmElementMounted()) {
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
            this.isChecked.subscribe(this.onPaymentMethodChange.bind(this));
            quote.paymentMethod.subscribe(function (                   newMethod                           ) {
                if (!newMethod || newMethod.method !== this.code) {
                    this.cleanupApmElement();
                }
            }.bind(this));
            quote.billingAddress.subscribe(function (                   newAddress                              ) {
                utils.hideYouPay();
                if (utils.isSameBillingAddress(this.lastBillingAddress, newAddress)) {
                    return;
                }
                this.lastBillingAddress = newAddress;
                this.cleanupApmElement();
                if (this.isChecked() && this.isPaymentBeforeOrder()) {
                    this.initPaymentBeforeOrderFlow();
                }
            }.bind(this));
            quote.totals.subscribe(function (                   newTotals     ) {
                if (!newTotals) {
                    return;
                }
                let newGrandTotal = newTotals.base_grand_total;
                if (this.lastGrandTotal !== null && this.lastGrandTotal !== newGrandTotal) {
                    this.cleanupApmElement();
                    if (this.isChecked() && this.isPaymentBeforeOrder()) {
                        this.initPaymentBeforeOrderFlow();
                    }
                }
                this.lastGrandTotal = newGrandTotal;
            }.bind(this));
            this.isApmContainerReady.subscribe(function (                   isReady         ) {
                if (isReady && this.isChecked() && !this.isApmElementMounted()) {
                    ko.tasks.schedule(() => {
                        this.tryMountApmElement();
                    });
                }
            }.bind(this));
            this.isApmElementMounted.subscribe(function (                   mounted         ) {
                if (!mounted) {
                    utils.removeCheckoutCurrencySwitcher();
                    utils.hideYouPay();
                }
            }.bind(this));
            if (this.isChecked()) {
                this.onPaymentMethodChange(true);
            }
            const intentId = utils.getQueryParam('intent_id');
            if (intentId && !window['awx_handling_intent_' + intentId]) {
                window['awx_handling_intent_' + intentId] = true;
                this.watchPaymentConfirmation(intentId, utils.getQueryParam('state'));
            }
            return this;
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
        onPaymentMethodChange(                   isSelected         ) {
            if (isSelected) {
                if (this.isPaymentBeforeOrder()) {
                    this.initPaymentBeforeOrderFlow();
                }
            } else {
                this.cleanupApmElement();
            }
        },
        unmountApmElement(                 ) {
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
            $(document).off('change.apm').on('change.apm', 'div[data-role=checkout-agreements] input[type="checkbox"]', function(                 ) {
                const $paymentMethod = $(this).closest('.payment-method');
                const isApmMethod = $paymentMethod.hasClass(self.getCode());
                if (!isApmMethod || !self.isChecked()) {
                    return;
                }
                self.agreementsChecked(self.areAllAgreementsChecked());
                self.tryMountApmElement();
            });
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
            if (this.isApmElementMounted()) {
                return;
            }
            const $body = $('body');
            $body.trigger('processStart');
            try {
                await this.initializeAirwallex();
                this.isApmElementMounted(true);
            } catch (e     ) {
                let msg = $t('Failed to initialize payment system. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                } else if (e && e.message) {
                    msg = e.message;
                }
                this.showError(msg);
            } finally {
                $body.trigger('processStop');
            }
        },
        async initializeAirwallex(                 ) {
            const intentResponse = await this.createPaymentIntent();
            if (!intentResponse.element_options) {
                throw new Error('Missing element options from server');
            }
            let elementOptions = intentResponse.element_options;
            if (typeof elementOptions === 'string') {
                elementOptions = JSON.parse(elementOptions);
            }
            await this.applyBillingCurrencyToElementOptions(elementOptions);
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
            const baseCurrency = (elementOptions.currency || '').toUpperCase();
            const billingCurrency = (this.getBillingCurrency() || '').toUpperCase();
            if (!billingCurrency || !baseCurrency || billingCurrency === baseCurrency) {
                return;
            }
            try {
                const quoteResp = await utils.conversionQuote(baseCurrency, billingCurrency);
                elementOptions.currency = billingCurrency;
                elementOptions.quote_id = quoteResp.id;
                this.currentCurrency = billingCurrency;
                this.initialConversionRate = quoteResp.conversion_rate;
            } catch (e) {
                // Pre-quote failed — drop back to order currency, don't show switcher
                this.currentCurrency = null;
                this.initialConversionRate = null;
                this.preQuoteFailed = true;
            }
        },
        async createPaymentIntent(                 ) {
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
            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }
            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());
            return await utils.getIntent(payload, {});
        },
        async createApmElement(                   elementOptions     ) {
            const self = this;
            try {
                this.apmElement = Airwallex.createElement('dropIn', elementOptions);
                this.apmElement.mount('airwallex-apm-element-checkout');
            } catch (e) {
                throw e;
            }
            let readyLogged = false;
            this.apmElement .on('ready', function() {
                if (readyLogged) {
                    return;
                }
                readyLogged = true;
            });
            this.apmElement .on('success', function() {
                $('body').trigger('processStart');
                utils.clearDataAfterPay({}, customerData);
                window.location.href = urlBuilder.build('airwallex/redirect?type=quote&id=' + quote.getQuoteId());
            });
            this.apmElement .on('quoteCreate', function(event) {
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
            const quoteCurrency = (this.getPaymentConfig().quote_currency_code || '').toUpperCase();
            const billingCurrency = (this.getBillingCurrency() || '').toUpperCase();
            if (!billingCurrency || billingCurrency === quoteCurrency) {
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
                    const rate = Number(this.initialConversionRate);
                    utils.showYouPay({
                        target_amount: grandTotal * rate,
                        target_currency: billingCurrency,
                        payment_currency: quoteCurrency,
                        client_rate: this.initialConversionRate,
                    }, $t);
                } catch (e) {
                    utils.hideYouPay();
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
            const effectiveCurrent = this.currentCurrency || baseCurrency;
            if (!this.apmElement || selected === effectiveCurrent) {
                return;
            }
            if (selected === baseCurrency) {
                this.currentCurrency = selected;
                this.apmElement.update({
                    currency: selected,
                    quote_id: undefined,
                });
                utils.hideYouPay();
                return;
            }
            const token = ++this.currencyToken;
            let quoteResp                         ;
            try {
                quoteResp = await utils.conversionQuote(baseCurrency, selected);
            } catch (e     ) {
                let msg = $t('Failed to convert currency. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                } else if (e && e.message) {
                    msg = e.message;
                }
                this.showError(msg);
                return;
            }
            if (token !== this.currencyToken) {
                return;
            }
            this.currentCurrency = selected;
            this.apmElement.update({
                quote_id: quoteResp.id,
                currency: selected,
            });
            try {
                const grandTotal = await this.fetchGrandTotal();
                const rate = Number(quoteResp.conversion_rate);
                utils.showYouPay({
                    target_amount: grandTotal * rate,
                    target_currency: selected,
                    payment_currency: baseCurrency,
                    client_rate: quoteResp.conversion_rate,
                }, $t);
            } catch (e) {
                utils.hideYouPay();
            }
        },
        async placeOrder(                   _data          , event        ) {
            if (event) {
                event.preventDefault();
            }
            if (this.isPaymentBeforeOrder()) {
                this.showError($t('Please complete payment using the payment method above.'));
                return false;
            }
            if (!this.validate() || !additionalValidators.validate()) {
                return false;
            }
            const $body = $('body');
            $body.trigger('processStart');
            try {
                await this._placeOrder();
            } catch (e     ) {
                let msg = $t('Something went wrong while processing your request. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                } else if (e && e.message) {
                    msg = e.message;
                } else if (typeof e === 'string') {
                    msg = e;
                }
                this.showError(msg);
                $body.trigger('processStop');
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
            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }
            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());
            const intentResponse = await utils.getIntent(payload, {});
            let apmUrl = urlBuilder.build('airwallex/apm/index');
            if (intentResponse.order_id) {
                apmUrl += '?order_id=' + intentResponse.order_id
                    + '&intent_id=' + encodeURIComponent(intentResponse.intent_id)
                    + '&state=' + encodeURIComponent(intentResponse.state || '');
            } else {
                apmUrl += '?quote_id=' + quote.getQuoteId()
                    + '&state=' + encodeURIComponent(intentResponse.state || '');
            }
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
