/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit,
 Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
 please view the LICENSE
 * file that was distributed with this source code.
 */

/* global Airwallex */
define(
    [
        'jquery',
        'ko',
        'Airwallex_Payments/js/view/payment/abstract-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/payment/place-order-hooks',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Airwallex_Payments/js/view/payment/utils',
        // 'Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry',
        'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    ],
    function (
        $,
        ko,
        Component,
        quote,
        additionalValidators,
        redirectOnSuccessAction,
        storage,
        customerData,
        placeOrderHooks,
        errorProcessor,
        urlBuilder,
        customer,
        utils,
        // recaptchaRegistry,
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
                this.validationError(undefined);

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
                const self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() &&
                    additionalValidators.validate() &&
                    this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);
                    $('body').trigger('processStart');

                    const payload = {
                        cartId: quote.getQuoteId(),
                        billingAddress: quote.billingAddress(),
                        paymentMethod: this.getData()
                    };

                    let serviceUrl;
                    if (customer.isLoggedIn()) {
                        serviceUrl = urlBuilder.createUrl('/airwallex/payments/place-order', {});
                    } else {
                        serviceUrl = urlBuilder.createUrl('/airwallex/payments/guest-place-order', {});
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

                            if (self.isSaveCardSelected() && self.getCustomerId()) {
                                await Airwallex.createPaymentConsent({
                                    intent_id: intentResponse.intent_id,
                                    customer_id: self.getCustomerId(),
                                    client_secret: intentResponse.client_secret,
                                    currency: quote.totals().quote_currency_code,
                                    billing: self.getBillingInformation(),
                                    element: self.cardElement,
                                    next_triggered_by: 'customer',
                                });
                            } else {
                                await Airwallex.confirmPaymentIntent({
                                    intent_id: intentResponse.intent_id,
                                    client_secret: intentResponse.client_secret,
                                    payment_method: {
                                        billing: self.getBillingInformation()
                                    },
                                    element: self.cardElement
                                });
                            }

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

                        self.afterPlaceOrder();

                        if (self.redirectAfterPlaceOrder) {
                            redirectOnSuccessAction.execute();
                        }
                    }).catch(
                        utils.processPlaceOrderError.bind(self)
                    ).finally(
                        function () {
                            $('body').trigger('processStop');
                            _.each(placeOrderHooks.afterRequestListeners, function (listener) {
                                listener();
                            });

                            self.isPlaceOrderActionAllowed(true);
                        }
                    );

                    return true;
                }

                return false;
            },
        });
    });
