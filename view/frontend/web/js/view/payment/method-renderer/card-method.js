/* global Airwallex */
define(
    [
        'jquery',
        'ko',
        'Airwallex_Payments/js/view/payment/abstract-method',
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
            mountElement: 'airwallex-payments-card-form',
            cvcMountElement: 'airwallex-payments-cvc-form',
            cardElement: undefined,
            cvcElement: undefined,
            cardDetail: {},
            validationError: ko.observable(),
            showNewCardForm: ko.observable(true),
            showCvcForm: ko.observable(false),
            isRecaptchaEnabled: !!window.checkoutConfig.payment.airwallex_payments.recaptcha_enabled,
            autoCapture: !!window.checkoutConfig.payment.airwallex_payments.cc_auto_capture,
            maxWidth: window.checkoutConfig.payment.airwallex_payments.card_max_width,
            defaults: {
                template: 'Airwallex_Payments/payment/card-method'
            },

            getCustomerId: function () {
                if (!customer.isLoggedIn()) {
                    return null;
                }
    
                return window.checkoutConfig.payment.airwallex_payments.airwallex_customer_id;
            },

            isAirwallexCustomer() {
                return !!this.getCustomerId();
            },

            getBillingInformation: function () {
                const billingAddress = quote.billingAddress();
                billingAddress.email = quote.guestEmail;
                addressHandler.setIntentConfirmBillingAddressFromOfficial(billingAddress);
                return addressHandler.intentConfirmBillingAddressFromOfficial;
            },

            initPayment: async function() {
                let fontSize =  window.checkoutConfig.payment.airwallex_payments.card_fontsize;
                if (window.airwallex_card_fontsize) {
                    fontSize = parseInt(window.airwallex_card_fontsize);
                    let min = 12;
                    let max = 20;
                    fontSize = fontSize < min ? min : fontSize;
                    fontSize = fontSize > max ? max : fontSize;
                }
                this.cardElement = Airwallex.createElement('card', {
                    autoCapture: this.autoCapture,
                    style: {
                        base: {
                            fontSize: fontSize + 'px',
                        }
                    }                       
                });
                this.cardElement.mount(this.mountElement);
                this.cardElement.on('change', (event) => {
                    this.cardDetail = event.detail;
                    if (this.cardDetail.complete) {
                        this.validationError('');
                    }
                })

                $('body').trigger('processStop');
                if (!this.isCardVaultActive()) {
                    return;
                }

                $("input[value=__new_card__]").prop('checked', true);
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

            placeOrder: function (data, event) {
                let self = this;
                this.validationError('');

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() &&
                    additionalValidators.validate() &&
                    this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);
                    if (!this.cardDetail || !this.cardDetail.complete) {
                        this.isPlaceOrderActionAllowed(true);
                        this.validationError($.mage.__('Please complete your payment details.'));
                        return
                    }
                    utils.pay(self, 'card', quote);
                    return true;
                }
                return false;
            },
        });
    });
