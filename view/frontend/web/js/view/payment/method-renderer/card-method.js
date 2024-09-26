/* global Airwallex */
define(
    [
        'jquery',
        'ko',
        'mage/url',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Airwallex_Payments/js/view/payment/utils',
        'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    ],
    function (
        $,
        ko,
        url,
        storage,
        Component,
        quote,
        additionalValidators,
        errorProcessor,
        customer,
        utils,
        addressHandler,
    ) {
        'use strict';

        return Component.extend({
            code: 'airwallex_payments_card',
            type: 'card',
            cardNumberSelector: 'awx-card-number',
            cardExpirySelector: 'awx-card-expiry',
            cardCvcSelector: 'awx-card-cvc',
            cardNumberElement: null,
            cardExpiryElement: null,
            cardCvcElement: null,
            isNumberComplete: ko.observable(false),
            isExpiryComplete: ko.observable(false),
            isCvcComplete: ko.observable(false),
            cardNumberDetail: {},
            cardExpiryDetail: {},
            cardCvcDetail: {},
            validationError: ko.observable(),
            autoCapture: !!window.checkoutConfig.payment.airwallex_payments.cc_auto_capture,
            maxWidth: window.checkoutConfig.payment.airwallex_payments.card_max_width,
            fonts: [{
                src: 'https://checkout.airwallex.com/fonts/CircularXXWeb/CircularXXWeb-Regular.woff2',
                family: 'AxLLCircular',
                weight: 400,
            }],

            defaults: {
                template: 'Airwallex_Payments/payment/card-method'
            },

            getCustomerId: function () {
                if (!customer.isLoggedIn()) {
                    return null;
                }
                return window.checkoutConfig.payment.airwallex_payments.airwallex_customer_id;
            },

            loadPayment() {
                Airwallex.init({
                    env: window.checkoutConfig.payment.airwallex_payments.mode,
                    origin: window.location.origin,
                    fonts: this.fonts
                });
                this.initPayment();
            },

            getBillingInformation: function () {
                const billingAddress = quote.billingAddress();
                if (!billingAddress) {
                    throw new Error('Billing address is required.');
                }
                billingAddress.email = quote.guestEmail;
                addressHandler.setIntentConfirmBillingAddressFromOfficial(billingAddress);
                return addressHandler.intentConfirmBillingAddressFromOfficial;
            },

            showNumberError() {
                return this.validationError() && !this.isNumberComplete();
            },

            showExpiryError() {
                return this.validationError() && !this.isExpiryComplete();
            },

            showCvcError() {
                return this.validationError() && !this.isCvcComplete();
            },

            initPayment: async function () {
                let fontSize = window.checkoutConfig.payment.airwallex_payments.card_fontsize;
                if (window.airwallex_card_fontsize) {
                    fontSize = parseInt(window.airwallex_card_fontsize);
                    let min = 12;
                    let max = 20;
                    fontSize = fontSize < min ? min : fontSize;
                    fontSize = fontSize > max ? max : fontSize;
                }
                this.cardNumberElement = Airwallex.createElement('cardNumber', {
                    autoCapture: this.autoCapture,
                    style: {
                        base: {
                            fontSize: fontSize + 'px',
                        },
                    }
                });
                this.cardExpiryElement = Airwallex.createElement('expiry', {
                    style: {
                        base: {
                            fontSize: fontSize + 'px',
                        }
                    }
                });
                this.cardCvcElement = Airwallex.createElement('cvc', {
                    style: {
                        base: {
                            fontSize: fontSize + 'px',
                        }
                    },
                    placeholder: 'CVC'
                });

                for (let type of ['Number', 'Expiry', 'Cvc']) {
                    this['card' + type + 'Element'].mount(this['card' + type + 'Selector']);
                    this['card' + type + 'Element'].on('change', (event) => {
                        this['card' + type + 'Detail'] = event.detail;
                        this['is' + type + 'Complete'](this['card' + type + 'Detail'].complete);
                        if (this.isNumberComplete() && this.isExpiryComplete() && this.isCvcComplete()) {
                            this.validationError('');
                        }
                    });

                    this['card' + type + 'Element'].on('focus', () => {
                        this.validationError('');
                        $("#awx-card-" + type.toLowerCase()).addClass("awx-focus-input");
                    });
                    this['card' + type + 'Element'].on('blur', () => {
                        $("#awx-card-" + type.toLowerCase()).removeClass("awx-focus-input");
                    });
                }

                this['cardNumberElement'].on('change', (e) => {
                    if (e.detail.complete) {
                        this['cardExpiryElement'].focus()
                    }
                });

                this['cardExpiryElement'].on('change', (e) => {
                    if (e.detail.complete) {
                        this['cardCvcElement'].focus()
                    }
                });

                this.cardNumberElement.on('ready', () => {
                    this.cardNumberElement.focus();
                });

                $('.airwallex-card-container .payment-method-title').click(() => {
                    this.cardNumberElement.focus();
                });
            },

            initiateOrderPlacement: async function () {
                const self = this;

                if (!additionalValidators.validate()) {
                    return;
                }
                this.validationError('');

                self.placeOrder();
            },

            isCardVaultActive() {
                if (!customer.isLoggedIn()) return false;
                return window.checkoutConfig.payment.airwallex_payments.is_card_vault_active;
            },

            isSaveCardSelected: function () {
                if (!this.isCardVaultActive()) {
                    return false;
                }
                return $('#airwallex-payments-card-save').is(':checked');
            },

            async placeOrder() {
                let self = this;
                this.validationError('');
                if (this.validate() && additionalValidators.validate()) {
                    if (!this.cardNumberDetail || !this.cardNumberDetail.complete
                        || !this.cardExpiryDetail || !this.cardExpiryDetail.complete
                        || !this.cardCvcDetail || !this.cardCvcDetail.complete) {
                        this.validationError($.mage.__('Please complete your payment details.'));
                        return;
                    }

                    await utils.pay(self, 'card', quote);
                    return true;
                }
                return false;
            },
        });
    });
