/*browser:true*/
define([
    'ko',
    'jquery',
    'mage/storage',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/payment/place-order-hooks',
    'Magento_Customer/js/model/customer',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler'
], function (
    ko,
    $,
    storage,
    VaultComponent,
    quote,
    urlBuilder,
    customerData,
    placeOrderHooks,
    customer,
    utils,
    addressHandler
) {
    'use strict';

    return VaultComponent.extend({
        validationError: ko.observable(),
        isRecaptchaEnabled: !!window.checkoutConfig.payment.airwallex_payments.recaptcha_enabled,
        autoCapture: !!window.checkoutConfig.payment.airwallex_payments.cc_auto_capture,
        cvcElement: undefined,
        cvcDetail: undefined,
        defaults: {
            active: false,
            template: 'Airwallex_Payments/payment/vault',
        },

        /**
         * @returns {exports}
         */
        initObservable: function () {
            this._super().observe(['active']);
            return this;
        },

        /**
         * Is payment option active?
         *
         * @returns {boolean}
         */
        isActive: function () {
            let active = this.getId() === this.isChecked();

            this.active(active);
            return active;
        },

        /**
         * Return the payment method code.
         *
         * @returns {string}
         */
        getCode: function () {
            return 'airwallex_cc_vault';
        },

        /**
         * Get last 4 digits of card
         *
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         *
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         *
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        /**
         * Get card icons
         *
         * @returns {String}
         */
        getIcons: function (type) {
            for (const [name, obj] of Object.entries(window.checkoutConfig.payment.ccform.icons)) {
                if ('AMEX' === type) {
                    type = 'AE';
                    break
                }
                if (obj.title.toLowerCase() === type.toLowerCase()) {
                    type = name;
                    break;
                }
            }
            return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(type) ?
                window.checkoutConfig.payment.ccform.icons[type]
                : false;
        },

        getBillingInformation: function () {
            const billingAddress = quote.billingAddress();
            billingAddress.email = quote.guestEmail;
            addressHandler.setIntentConfirmBillingAddressFromOfficial(billingAddress);
            return addressHandler.intentConfirmBillingAddressFromOfficial;
        },

        isAirwallexCustomerIdSame() {
            return this.details.customer_id === window.checkoutConfig.payment.airwallex_payments.airwallex_customer_id;
        },

        initCvcForm: function (id) {
            $('body').trigger('processStart');
            if (this.cvcElement) this.cvcElement.destroy();
            Airwallex.init({
                env: window.checkoutConfig.payment.airwallex_payments.mode,
                origin: window.location.origin,
            });

            this.cvcElement = Airwallex.createElement('cvc');
            const domElement = this.cvcElement.mount(id + '-cvc', { autoCapture: this.autoCapture });
            domElement.addEventListener('onReady', (event) => {
                $('body').trigger('processStop');
              });
            this.cvcElement.on('change', (event) => {
                this.cvcDetail = event.detail;
                if (this.cvcDetail.complete) {
                    this.validationError('');
                }
            })
        },

        placeOrder: function (data, event) {
            const self = this;

            if (event) {
                event.preventDefault();
            }

            if (!this.cvcDetail || !this.cvcDetail.complete) {
                this.validationError($.mage.__('Card Verification Code is incomplete.'));
                return
            }

            $('body').trigger('processStart');

            const payload = {
                cartId: quote.getQuoteId(),
                billingAddress: quote.billingAddress(),
                paymentMethod: {
                    method: 'airwallex_payments_card',
                    additional_data: {},
                },
            };

            let serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order');
            if (customer.isLoggedIn()) {
                serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
                payload.email = quote.guestEmail;
            }

            let headers = {};
            _.each(placeOrderHooks.requestModifiers, function (modifier) {
                modifier(headers, payload);
            });

            payload.intent_id = null;

            (new Promise(async function (resolve, reject) {
                try {
                    if (self.isRecaptchaEnabled) {
                        let recaptchaRegistry = require('Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry');
                        if (recaptchaRegistry) {
                            payload.xReCaptchaValue = await new Promise((resolve, reject) => {
                                recaptchaRegistry.addListener(utils.getRecaptchaId(), (token) => {
                                    resolve(token);
                                });
                                recaptchaRegistry.triggers[utils.getRecaptchaId()]();
                            });
                        }
                    }

                    const intentResponse = await storage.post(
                        serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                    );

                    const selectedConsentId = $("#v-" + $('input[name="payment[method]"]:checked').val()).val();
                    const response = await Airwallex.confirmPaymentIntent({
                        intent_id: intentResponse.intent_id,
                        client_secret: intentResponse.client_secret,
                        payment_consent_id: selectedConsentId,
                        element: self.cvcElement,
                        payment_method: {
                            billing: self.getBillingInformation()
                        },
                        payment_method_options: {
                            card: {
                                auto_capture: self.autoCapture
                            }
                        },
                    });

                    payload.intent_id = intentResponse.intent_id;
                    payload.paymentMethod.additional_data.intent_id = intentResponse.intent_id;

                    const endResult = await storage.post(
                        serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                    );

                    resolve(endResult);
                } catch (e) {
                    reject(e);
                }
            })).then(function (response) {
                utils.clearDataAfterPay(response, customerData)

                window.location.replace(urlBuilder.build('checkout/onepage/success/'));
            }).catch(
                utils.processPlaceOrderError.bind(self)
            );
        }
    });
});
