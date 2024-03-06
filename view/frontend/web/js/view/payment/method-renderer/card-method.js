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
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Customer/js/model/customer',
        'Airwallex_Payments/js/view/payment/method-renderer/card-method-recaptcha'
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
        fullScreenLoader,
        urlBuilder,
        customer,
        cardMethodRecaptcha
    ) {
        'use strict';

        return Component.extend({
            code: 'airwallex_payments_card',
            type: 'card',
            mountElement: 'airwallex-payments-card-form',
            cardElement: undefined,
            validationError: ko.observable(),
            isRecaptchaEnabled: !!window.checkoutConfig?.payment?.airwallex_payments?.recaptcha_enabled,
            recaptcha: null,
            defaults: {
                template: 'Airwallex_Payments/payment/card-method'
            },

            getBillingInformation: function () {
                const billingAddress = quote.billingAddress();

                return {
                    address: {
                        city: billingAddress.city,
                        country_code: billingAddress.countryId,
                        postcode: billingAddress.postcode,
                        state: billingAddress.region,
                        street: billingAddress.street[0]
                    },
                    first_name: billingAddress.firstname,
                    last_name: billingAddress.lastname,
                    email: quote.guestEmail
                }
            },

            initPayment: async function () {
                this.cardElement = Airwallex.createElement(
                    this.type,
                    {
                        autoCapture: window.checkoutConfig.payment.airwallex_payments.cc_auto_capture
                    }
                );
                this.cardElement.mount(this.mountElement);
                this.recaptcha = cardMethodRecaptcha();
                this.recaptcha.renderReCaptcha();

                window.addEventListener(
                    'onReady',
                    function () {
                        $('body').trigger('processStop');
                    }
                );
            },

            initiateOrderPlacement: async function () {
                const self = this;

                if (!additionalValidators.validate()) {
                    return;
                }
                this.validationError(undefined);

                self.placeOrder();
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
                    fullScreenLoader.startLoader();

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
                            const xReCaptchaValue = await (new Promise(function (resolve) {
                                self.getRecaptchaToken(resolve);
                            }));
                            payload.xReCaptchaValue = xReCaptchaValue;
                            console.debug("ReCaptcha", xReCaptchaValue);

                            const intentResponse = await storage.post(
                                serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                            );
                            console.debug("Intent Response", intentResponse);

                            const params = {};
                            params.id = intentResponse.intent_id;
                            params.client_secret = intentResponse.client_secret;
                            params.payment_method = {};
                            params.payment_method.billing = self.getBillingInformation();
                            params.element = self.cardElement;

                            payload.intent_id = intentResponse.intent_id;
                            payload.xReCaptchaValue = null;

                            const airwallexResponse = await Airwallex.confirmPaymentIntent(params);
                            console.debug("airwallexResponse", airwallexResponse);

                            const endResult = await storage.post(
                                serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                            );
                            console.debug("endResult", endResult);

                            resolve(endResult);
                        } catch (e) {
                            reject(e);
                        }
                    })).then(function (response) {
                        const clearData = {
                            'selectedShippingAddress': null,
                            'shippingAddressFromData': null,
                            'newCustomerShippingAddress': null,
                            'selectedShippingRate': null,
                            'selectedPaymentMethod': null,
                            'selectedBillingAddress': null,
                            'billingAddressFromData': null,
                            'newCustomerBillingAddress': null
                        };

                        if (response?.responseType !== 'error') {
                            customerData.set('checkout-data', clearData);
                            customerData.invalidate(['cart']);
                            customerData.reload(['cart'], true);
                        }

                        self.afterPlaceOrder();

                        if (self.redirectAfterPlaceOrder) {
                            redirectOnSuccessAction.execute();
                        }
                    }).catch(
                        self.processPlaceOrderError.bind(self)
                    ).finally(
                        function () {
                            fullScreenLoader.stopLoader();
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

            processPlaceOrderError: function (response) {
                fullScreenLoader.stopLoader();
                $('body').trigger('processStop');

                if (response?.getResponseHeader) {
                    errorProcessor.process(response, this.messageContainer);
                    const redirectURL = response.getResponseHeader('errorRedirectAction');

                    if (redirectURL) {
                        setTimeout(function () {
                            errorProcessor.redirectTo(redirectURL);
                        }, 3000);
                    }
                } else if (response?.message) {
                    this.validationError(response.message);
                }
            },

            getRecaptchaToken: function (callback) {
                if (!this.isRecaptchaEnabled) {
                    return callback();
                }

                const reCaptchaId = this.recaptcha.getReCaptchaId(),
                      registry = this.recaptcha.getRegistry();

                if (registry.tokens.hasOwnProperty(reCaptchaId)) {
                    const response = registry.tokens[reCaptchaId];
                    if (typeof response === 'object' && typeof response.then === 'function') {
                        response.then(function (token) {
                            callback(token);
                        });
                    } else {
                        callback(response);
                    }
                } else {
                    registry._listeners[reCaptchaId] = callback;
                    registry.triggers[reCaptchaId]();
                }
            }
        });
    });
