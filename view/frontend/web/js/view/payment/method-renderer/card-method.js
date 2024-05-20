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
        'Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry',
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
        recaptchaRegistry,
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
            savedCards: ko.observableArray(),
            validationError: ko.observable(),
            showNewCardForm: ko.observable(true),
            showCvcForm: ko.observable(false),
            isRecaptchaEnabled: !!window.checkoutConfig.payment.airwallex_payments.recaptcha_enabled,
            recaptchaId: 'recaptcha-checkout-place-order',
            isCvcRequired: !!window.checkoutConfig.payment.airwallex_payments.cvc_required,
            autoCapture: !!window.checkoutConfig.payment.airwallex_payments.cc_auto_capture,
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
                this.cardElement = Airwallex.createElement('card', {autoCapture: this.autoCapture});
                this.cardElement.mount(this.mountElement);
   
                if (this.getCustomerId()) {
                    await this.loadSavedCards();
                }
                $('body').trigger('processStop');

                $(".airwallex-payments-saved-card-item").click((e) => {
                    var inputValue = $(e.currentTarget).find('input[type="radio"]').val();
                    this.showNewCardForm(inputValue === '__new_card__');
                });

                $("input[value=__new_card__]").prop('checked', true);
            },

            getRecaptchaId() {
                if ($('#recaptcha-checkout-place-order').length) {
                    return this.recaptchaId;
                }
                return $('.airwallex-card-container .g-recaptcha').attr('id');
            },

            initiateOrderPlacement: async function () {
                const self = this;

                if (!additionalValidators.validate()) {
                    return;
                }
                this.validationError(undefined);

                self.placeOrder();
            },

            initCvcForm: function() {
                if (!this.getCustomerId() || !this.isCvcRequired || this.cvcElement !== undefined) {
                    return;
                }

                this.cvcElement = Airwallex.createElement('cvc');
                this.cvcElement.mount(this.cvcMountElement, {autoCapture: this.autoCapture});
            },

            loadSavedCards: async function () {
                const savedCards = await storage.get(urlBuilder.createUrl('/airwallex/saved_cards', {}));

                if (savedCards && savedCards.length) {
                    savedCards.forEach((consent) => {
                        const l4Pad = consent.card_brand.toLowerCase() === 'american express' ? '**** ******* *' : '**** **** **** ';

                        this.savedCards.push({
                            consent_id: consent.id,
                            brand: consent.card_brand,
                            expiry: '('+consent.card_expiry_month + '/' + consent.card_expiry_year.substring(2)+')',
                            last4: l4Pad + consent.card_last_four,
                            icon: consent.card_icon
                        });
                    })
                }
            },

            getSelectedSavedCard: function () {
                const selectedConsentId = $('input[name="airwallex-selected-card"]:checked').val();

                if (!selectedConsentId || selectedConsentId === '__new_card__') {
                    return null;
                }

                return selectedConsentId;
            },

            isSaveCardSelected: function () {
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
                                payload.xReCaptchaValue = await new Promise((resolve, reject) => {
                                    recaptchaRegistry.addListener(self.getRecaptchaId(), (token) => {
                                        resolve(token);
                                    });
                                    recaptchaRegistry.triggers[self.getRecaptchaId()]();
                                });
                            }

                            const intentResponse = await storage.post(
                                serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                            );

                            const selectedConsentId = self.getSelectedSavedCard();
                            if (selectedConsentId) {
                                const response = await Airwallex.confirmPaymentIntent({
                                    intent_id: intentResponse.intent_id,
                                    client_secret: intentResponse.client_secret,
                                    payment_consent_id: selectedConsentId,
                                    element: self.isCvcRequired ? self.cvcElement : undefined,
                                    payment_method: {
                                        billing: self.getBillingInformation()
                                    },
                                    payment_method_options: {
                                        card: {
                                            auto_capture: self.autoCapture
                                        }
                                    },
                                });
                            } else if (self.isSaveCardSelected()) {
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

                        if (response && response.responseType !== 'error') {
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
                $('body').trigger('processStop');

                if (response && response.getResponseHeader) {
                    errorProcessor.process(response, this.messageContainer);
                    const redirectURL = response.getResponseHeader('errorRedirectAction');

                    if (redirectURL) {
                        setTimeout(function () {
                            errorProcessor.redirectTo(redirectURL);
                        }, 3000);
                    }
                } else if (response && response.message) {
                    this.validationError(response.message);
                }
            },
        });
    });
