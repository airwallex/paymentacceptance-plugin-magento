define(
    [
        'jquery',
        'ko',
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
        'uiComponent',
        'Airwallex_Payments/js/view/payment/method-renderer/card-method-recaptcha'
    ],
    function (
        $,
        ko,
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
        Component,
        cardMethodRecaptcha
    ) {
        'use strict';
        return Component.extend({
            code: 'airwallex_payments_express',
            defaults: {
                paymentConfig: window.checkoutConfig.payment.airwallex_payments,
                totalsData: window.checkoutConfig.totalsData,
                template: 'Airwallex_Payments/payment/express-checkout',
                recaptcha: null,
                validationError: ko.observable(),
                googlepay: null,
                redirectAfterPlaceOrder: true,
                shipment: {
                    "shipping_address": {},
                    "billing_address": {},
                    "shipping_method_code": '',
                    "shipping_carrier_code": ''
                },
                isExpressLoaded: false
            },

            getOptions() {
                return {
                    mode: 'payment',
                    buttonColor: this.paymentConfig.express_style.google_pay_button_theme,
                    buttonType: this.paymentConfig.express_style.google_pay_button_type,
                    emailRequired: true,
                    billingAddressRequired: true,
                    billingAddressParameters: {
                        format: 'FULL',
                        phoneNumberRequired: this.paymentConfig.is_express_phone_required
                    },
                    merchantInfo: {
                        merchantName: this.paymentConfig.express_seller_name || '',
                    },
                    autoCapture: this.paymentConfig.is_express_capture_enabled,
                }
            },

            getGooglePayRequestOptions: function () {
                let paymentDataRequest = this.getOptions()

                paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
                if (this.isShippingRequired()) {
                    paymentDataRequest.callbackIntents.push('SHIPPING_ADDRESS', 'SHIPPING_OPTION');
                    paymentDataRequest.shippingAddressRequired = true;
                    paymentDataRequest.shippingOptionRequired = true;
                    paymentDataRequest.shippingAddressParameters = {
                        phoneNumberRequired: this.paymentConfig.is_express_phone_required,
                    };
                }

                const transactionInfo = {
                    amount: {
                        value: this.getGrandTotal(),
                        currency: this.getCurrencyCode(),
                    },
                    countryCode: this.getCountryCode(),
                    displayItems: this.getDisplayItems(),
                };

                return Object.assign(paymentDataRequest, transactionInfo);
            },

            getExpressButtonSort() {
                return this.paymentConfig.express_button_sort
            },

            getCountryCode() {
                return this.paymentConfig.country_code
            },

            getDisplayItems() {
                let res = [];
                for (let i = 0; i < this.totalsData.total_segments.length; i++) {
                    let el = this.totalsData.total_segments[i];
                    el.value = this.formatPrice(el.value).toString()
                    if (el.code === 'shipping') {
                        res.push({'label': 'Shipping', 'type': 'LINE_ITEM', 'price': el.value})
                    } else if (el.code === 'tax') {
                        res.push({'label': 'Tax', 'type': 'TAX', 'price': el.value})
                    } else if (el.code === 'subtotal') {
                        res.push({'label': 'Subtotal', 'type': 'SUBTOTAL', 'price': el.value})
                    }
                }
                return res
            },

            formatPrice(p) {
                return parseFloat(parseFloat(p).toFixed(2))
            },

            isExpressActive() {
                return this.paymentConfig.is_express_active
            },

            getGrandTotal() {
                let res = 0
                for (let i = 0; i < this.totalsData.total_segments.length; i++) {
                    let el = this.totalsData.total_segments[i];
                    if (el.code === 'grand_total') {
                        res = el.value
                        break
                    }
                }
                return parseFloat(res).toFixed(2)
            },

            getCurrencyCode() {
                return this.totalsData.quote_currency_code
            },

            isShippingRequired() {
                return !quote.isVirtual() && location.hash !== '#payment'
            },

            formatShippingOptions(address) {
                const addresses = Array.isArray(address) ? address : [address];
                const shippingOptions = addresses.map(addr => {
                    return {
                        id: addr.carrier_code,
                        label: addr.method_code,
                        description: addr.carrier_title,
                    };
                });

                let defaultSelectedOptionId = shippingOptions.length > 0 ? shippingOptions[0].id : undefined;
                shippingOptions.forEach(option => {
                    if (option.id === window.checkoutConfig.selectedShippingMethod.carrier_code) {
                        defaultSelectedOptionId = option.id;
                    }
                });

                return {
                    shippingOptions,
                    defaultSelectedOptionId
                };
            },

            observePayment() {
                let targetNode = document.getElementById('payment');
                let config = {attributes: true, attributeFilter: ['style']};

                let callback = (mutationsList, observer) => {
                    for (let mutation of mutationsList) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            let displayStyle = window.getComputedStyle(targetNode).display;
                            if (displayStyle !== 'none') {
                                if (!this.isExpressLoaded) {
                                    this.loadPayment()
                                }
                            } else {
                                this.isExpressLoaded = false
                                Airwallex.destroyElement('googlePayButton');
                            }
                        }
                    }
                };

                let observer = new MutationObserver(callback);

                observer.observe(targetNode, config);
            },

            loadPayment() {
                this.isExpressLoaded = true

                this.recaptcha = cardMethodRecaptcha();
                this.recaptcha.renderReCaptcha();

                Airwallex.init({
                    env: this.paymentConfig.mode,
                    origin: window.location.origin,
                });

                let options = this.getGooglePayRequestOptions();
                // options.shippingOptionParameters = this.formatShippingOptions(window.checkoutConfig.selectedShippingMethod)
                const googlepay = Airwallex.createElement('googlePayButton', options)
                this.googlepay = googlepay
                googlepay.mount('awx-google-pay');

                googlepay.on('shippingAddressChange', async (event) => {
                    let eventAddress = event.detail.intermediatePaymentData.shippingAddress
                    let address = {
                        "region": eventAddress.administrativeArea,
                        "country_id": eventAddress.countryCode,
                        "postcode": eventAddress.postalCode,
                        "city": eventAddress.locality,
                    }
                    const resp = await storage.post(
                        '/rest/default/V1/carts/mine/estimate-shipping-methods', JSON.stringify({address}), true, 'application/json'
                    );

                    let options = this.getGooglePayRequestOptions();
                    options.shippingOptionParameters = this.formatShippingOptions(resp)
                    googlepay.update(options);
                });

                googlepay.on('shippingMethodChange', async (event) => {
                    googlepay.update();
                });

                googlepay.on('authorized', async (event) => {
                    this.placeOrder()
                });
            },

            getData() {
                return {
                    "method": this.code,
                    "po_number": null,
                    "additional_data": {
                        "amount": 0,
                        "intent_status": 0
                    }
                }
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

            placeOrder: function (data, event) {
                const self = this;

                if (event) {
                    event.preventDefault();
                }

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

                        const intentResponse = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', headers
                        );
                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;
                        params.payment_method = {};
                        params.payment_method.billing = self.getBillingInformation();

                        payload.intent_id = intentResponse.intent_id;
                        payload.xReCaptchaValue = null;

                        const airwallexResponse = await self.googlepay.confirmIntent(params);

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

                    if (response?.responseType !== 'error') {
                        customerData.set('checkout-data', clearData);
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart'], true);
                    }

                    if (self.redirectAfterPlaceOrder) {
                        redirectOnSuccessAction.execute();
                    }
                }).catch(
                    self.processPlaceOrderError.bind(self)

                ).finally(
                    function () {
                        self.recaptcha.reset();
                        fullScreenLoader.stopLoader();
                        $('body').trigger('processStop');
                        _.each(placeOrderHooks.afterRequestListeners, function (listener) {
                            listener();
                        });

                    }
                );
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
        });
    }
);
