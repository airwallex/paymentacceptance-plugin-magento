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
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'ko',
    'mage/url',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (
    Component,
    $,
    ko,
    urlBuilder,
    utils,
    additionalValidators,
    addressHandler,
    quote,
    customerData,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Airwallex_Payments/payment/apm-method',
            code: 'airwallex_payments_apm',
        },

        apmElement: null,
        isApmElementMounted: ko.observable(false),
        isApmContainerReady: ko.observable(false),
        agreementsChecked: ko.observable(false),
        lastBillingAddress: null,
        lastGrandTotal: null,
        agreementsBound: false,

        getCode() {
            return this.code;
        },

        getPaymentConfig() {
            if (window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.airwallex_payments) {
                return window.checkoutConfig.payment.airwallex_payments;
            }
            return {};
        },

        shouldShowLogos() {
            return this.getPaymentMethodLogos().length > 0;
        },

        getPaymentMethodLogos() {
            const paymentConfig = this.getPaymentConfig();
            return paymentConfig.apm_selected_logos || [];
        },

        isPaymentBeforeOrder() {
            const paymentConfig = this.getPaymentConfig();
            return paymentConfig.is_order_before_payment === false;
        },

        isAgreementsEnabled() {
            const config = window.checkoutConfig && window.checkoutConfig.checkoutAgreements;
            return !!(config && config.isEnabled && config.agreements && config.agreements.length > 0);
        },

        hasAgreements() {
            return this.getAgreementCheckboxes().length > 0;
        },

        getAgreementCheckboxes() {
            return $('.payment-method._active div[data-role=checkout-agreements] input[type="checkbox"]');
        },

        hasBillingAddress() {
            const billing = quote.billingAddress();
            return !!(billing && billing.countryId);
        },

        canMountApmElement() {
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

        shouldShowApmElement() {
            return this.isApmContainerReady() || this.isApmElementMounted();
        },

        onApmContainerRendered() {
            this.watchAgreementChanges();
            this.tryMountApmElement();
        },

        tryMountApmElement() {
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

        initialize() {
            this._super();

            this.isChecked.subscribe(this.onPaymentMethodChange.bind(this));

            quote.paymentMethod.subscribe(function (newMethod) {
                if (!newMethod || newMethod.method !== this.code) {
                    this.cleanupApmElement();
                }
            }.bind(this));

            quote.billingAddress.subscribe(function (newAddress) {
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

            quote.totals.subscribe(function (newTotals) {
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

            this.isApmContainerReady.subscribe(function (isReady) {
                if (isReady && this.isChecked() && !this.isApmElementMounted()) {
                    ko.tasks.schedule(() => {
                        this.tryMountApmElement();
                    });
                }
            }.bind(this));

            if (this.isChecked()) {
                this.onPaymentMethodChange(true);
            }

            return this;
        },

        onPaymentMethodChange(isSelected) {
            if (isSelected) {
                if (this.isPaymentBeforeOrder()) {
                    this.initPaymentBeforeOrderFlow();
                }
            } else {
                this.cleanupApmElement();
            }
        },

        unmountApmElement() {
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

        cleanupApmElement() {
            this.unmountApmElement();
            this.isApmContainerReady(false);
        },

        selectPaymentMethod() {
            this._super();

            if (this.isPaymentBeforeOrder()) {
                this.initPaymentBeforeOrderFlow();
            }

            return true;
        },

        initPaymentBeforeOrderFlow() {
            this.isApmContainerReady(true);
        },

        watchAgreementChanges() {
            if (this.agreementsBound) {
                return;
            }
            this.agreementsBound = true;

            const self = this;

            $(document).off('change.apm').on('change.apm', 'div[data-role=checkout-agreements] input[type="checkbox"]', function() {
                const $paymentMethod = $(this).closest('.payment-method');
                const isApmMethod = $paymentMethod.hasClass(self.getCode());

                if (!isApmMethod || !self.isChecked()) {
                    return;
                }

                self.agreementsChecked(self.areAllAgreementsChecked());
                self.tryMountApmElement();
            });
        },

        areAllAgreementsChecked() {
            const $agreements = this.getAgreementCheckboxes();
            if ($agreements.length === 0) {
                return !this.isAgreementsEnabled();
            }

            let allChecked = true;
            $agreements.each(function() {
                if (!$(this).prop('checked')) {
                    allChecked = false;
                    return false;
                }
            });
            return allChecked;
        },

        async mountApmElement() {
            if (this.isApmElementMounted()) {
                return;
            }

            const $body = $('body');
            $body.trigger('processStart');

            try {
                await this.initializeAirwallex();
                this.isApmElementMounted(true);
            } catch (e) {
                let msg = $t('Failed to initialize payment system. Please try again.');
                if (e && e.message) {
                    msg = e.message;
                }
                this.showError(msg);
            } finally {
                $body.trigger('processStop');
            }
        },

        async initializeAirwallex() {
            const intentResponse = await this.createPaymentIntent();

            if (!intentResponse.element_options) {
                throw new Error('Missing element options from server');
            }

            let elementOptions = intentResponse.element_options;
            if (typeof elementOptions === 'string') {
                elementOptions = JSON.parse(elementOptions);
            }

            const paymentConfig = this.getPaymentConfig();
            const env = paymentConfig.mode === 'demo' ? 'demo' : 'prod';

            Airwallex.init({
                env: env,
                origin: window.location.origin,
            });

            this.createApmElement(elementOptions);
        },

        async createPaymentIntent() {
            const payload = {
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

        createApmElement(elementOptions) {
            const self = this;

            try {
                this.apmElement = Airwallex.createElement('dropIn', elementOptions);

                this.apmElement.mount('airwallex-apm-element-checkout');
            } catch (e) {
                throw e;
            }

            let readyLogged = false;
            this.apmElement.on('ready', function() {
                if (readyLogged) {
                    return;
                }
                readyLogged = true;
            });

            this.apmElement.on('success', function() {
                $('body').trigger('processStart');
                utils.clearDataAfterPay({}, customerData);
                window.location.href = urlBuilder.build('airwallex/redirect?type=quote&id=' + quote.getQuoteId());
            });

            this.apmElement.on('quoteCreate', function(event) {
                const quote = event?.detail?.quote;
                if (quote) {
                    utils.showYouPay(quote, $t);
                } else {
                    utils.hideYouPay();
                }
            });
        },

        async placeOrder(_data, event) {
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
            } catch (e) {
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

        async _placeOrder() {
            const payload = {
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
                apmUrl += '?order_id=' + intentResponse.order_id;
            } else {
                apmUrl += '?quote_id=' + quote.getQuoteId();
            }

            utils.clearDataAfterPay({}, customerData);
            location.href = apmUrl;
        },

        showError(message) {
            const $errorContainer = $('#airwallex-apm-error');
            if (!$errorContainer.length) {
                return;
            }

            $errorContainer.text(message);
            $errorContainer.show();
        },

        getData() {
            return {
                'method': this.item.method,
                'additional_data': {}
            };
        },

        disposeSubscriptions() {
            this._super();
            this.cleanupApmElement();
        }
    });
});
