define(
    [
        'jquery',
        'ko',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/error-processor', // todo
        'mage/url',
        'uiComponent',
        'Airwallex_Payments/js/view/payment/method-renderer/card-method-recaptcha' // todo
    ],
    function (
        $,
        ko,
        storage,
        customerData,
        errorProcessor,
        url,
        Component,
        cardMethodRecaptcha
    ) {
        'use strict';
        return Component.extend({
            code: 'airwallex_payments_express',
            defaults: {
                paymentConfig: {},
                recaptcha: null,
                validationError: ko.observable(),
                isExpressActive: ko.observable(false),
                googlepay: null,
                isExpressLoaded: false,
                quoteRemote: {},
                guestEmail: "", // todo how about the checkout page
                billingAddress: {},
                shippingMethods: [],
                methods: [],
                storeCode: "",
            },

            createUrl(url) {
                return '/rest/' + this.storeCode + '/V1' + url
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
                        value: this.formatCurrency(this.quoteRemote.grand_total),
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
                for (let key in this.quoteRemote) {
                    if (this.quoteRemote[key] === '0.0000') {
                        continue
                    }
                    if (key === 'shipping_amount') {
                        res.push({
                            'label': 'Shipping',
                            'type': 'LINE_ITEM',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'tax_amount') {
                        res.push({
                            'label': 'Tax',
                            'type': 'TAX',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'subtotal') {
                        res.push({
                            'label': 'Subtotal',
                            'type': 'SUBTOTAL',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'grand_subtotal_with_discount') {
                        if (this.quoteRemote[key] !== this.quoteRemote['subtotal']) {
                            res.push({
                                'label': 'Discount',
                                'type': 'LINE_ITEM',
                                'price': '-' + this.getDiscount().toString()
                            })
                        }
                    }
                }
                return res
            },

            getDiscount() {
                let diff = this.quoteRemote['subtotal'] - this.quoteRemote['grand_subtotal_with_discount']
                return diff.toFixed(2)
            },

            formatPrice(p) {
                return parseFloat(parseFloat(p).toFixed(2))
            },

            formatCurrency(v) {
                return parseFloat(v).toFixed(2)
            },

            getCurrencyCode() {
                return this.quoteRemote.quote_currency_code
            },

            isShippingRequired() {
                return !this.quoteRemote.is_virtual && location.hash !== '#payment'
            },


            setGuestEmail(email) {
                this.guestEmail = email
            },

            formatShippingMethodsToGoogle(methods, selectedMethod) {
                const shippingOptions = methods.map(addr => {
                    return {
                        id: addr.carrier_code,
                        label: addr.method_code,
                        description: addr.carrier_title,
                    };
                });

                return {
                    shippingOptions,
                    defaultSelectedOptionId: selectedMethod.carrier_code
                };
            },

            observePayment() {
                let self = this;
                let targetNode = document.getElementById('payment');
                let config = {attributes: true, attributeFilter: ['style']};

                let callback = (mutationsList, observer) => {
                    for (let mutation of mutationsList) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            let displayStyle = window.getComputedStyle(targetNode).display;
                            if (displayStyle !== 'none') {
                                if (!this.isExpressLoaded) {
                                    self.loadPayment()
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

            async fetchQuote() {
                let url = this.createUrl('/airwallex/payments/get-quote', {})
                const resp = await storage.get(
                    url, true, 'application/json', {}
                );
                let obj = JSON.parse(resp)
                this.quoteRemote = obj
                this.paymentConfig = obj.settings
            },

            postShippingInformation(payload) {
                let url = '/carts/mine/shipping-information';
                if (!this.isLoggedIn()) {
                    url = '/guest-carts/' + this.getCartId() + '/shipping-information'
                }
                return storage.post(
                    this.createUrl(url, {}), JSON.stringify(payload)
                );
            },

            estimateShippingMethods(address) {
                let url = '/carts/mine/estimate-shipping-methods';
                if (!this.isLoggedIn()) {
                    url = '/guest-carts/' + this.getCartId() + '/estimate-shipping-methods'
                }
                return storage.post(
                    this.createUrl(url, {}),
                    JSON.stringify({address})
                );
            },

            async loadPayment() {
                if (this.isExpressLoaded) {
                    return;
                }
                this.isExpressLoaded = true
                let parts = url.build('')
                    .split('/')
                    .filter(function (element) {
                        return element.length > 0;
                    });
                if (parts.length === 3) {
                    this.storeCode = parts[parts.length - 1]
                }

                let self = this;
                await this.fetchQuote();
                this.isExpressActive(this.paymentConfig.is_express_active)

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

                let updateQuoteByShipment = async (event) => {
                    // 1. estimateShippingMethods
                    let addr = self.getIntermediateShippingAddressFromGoogle(event.detail.intermediatePaymentData.shippingAddress)
                    this.methods = await this.estimateShippingMethods(addr)
                    // 2. postShippingInformation
                    let {information, selectedMethod} = self.constructAddressInformationForGoogle(
                        event.detail.intermediatePaymentData,
                        this.methods
                    )
                    let newQuote = await self.postShippingInformation(information)
                    // 3. update quote
                    await self.fetchQuote()

                    let options = this.getGooglePayRequestOptions();
                    options.shippingOptionParameters = this.formatShippingMethodsToGoogle(this.methods, selectedMethod)
                    googlepay.update(options);
                }

                googlepay.on('shippingAddressChange', updateQuoteByShipment);

                googlepay.on('shippingMethodChange', updateQuoteByShipment);

                googlepay.on('authorized', async (event) => {
                    self.setGuestEmail(event.detail.paymentData.email)

                    if (self.isShippingRequired()) {
                        let {
                            information,
                            selectedMethod
                        } = self.constructAddressInformationForGoogle(event.detail.paymentData, this.methods)
                        await self.postShippingInformation(information)

                        self.setBillingAddressFromGoogle(event.detail.paymentData);
                    }
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

            getCartId() {
                return this.isLoggedIn() ? this.quoteRemote.cart_id : this.quoteRemote.mask_cart_id
            },

            isLoggedIn() {
                return !!this.quoteRemote.customer_id;
            },

            placeOrder: function (data, event) {
                const self = this;

                if (event) {
                    event.preventDefault();
                }

                $('body').trigger('processStart');

                const payload = {
                    cartId: this.getCartId(),
                    paymentMethod: this.getData()
                };

                let serviceUrl;
                if (this.isLoggedIn()) {
                    serviceUrl = this.createUrl('/airwallex/payments/place-order', {});
                } else {
                    serviceUrl = this.createUrl('/airwallex/payments/guest-place-order', {});
                }

                payload.intent_id = null;

                (new Promise(async function (resolve, reject) {
                    try {
                        payload.xReCaptchaValue = await (new Promise(function (resolve) {
                            self.getRecaptchaToken(resolve);
                        }));
                        if (!self.isLoggedIn()) {
                            payload.email = self.guestEmail;
                        }

                        const intentResponse = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json'
                        );
                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;
                        params.payment_method = {};
                        params.payment_method.billing = self.billingAddress;

                        payload.intent_id = intentResponse.intent_id;
                        payload.xReCaptchaValue = null;
                        if (self.isShippingRequired()) {
                            payload.billingAddress = self.getBillingAddressToPlaceOrder()
                        } else {
                            // payload.billingAddress = quote.billingAddress();
                        }
                        await self.googlepay.confirmIntent(params);

                        const endResult = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
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

                    window.location.replace(url.build( '/checkout/onepage/success/'));
                }).catch(
                    self.processPlaceOrderError.bind(self)
                ).finally(
                    function () {
                        self.recaptcha.reset();
                        $('body').trigger('processStop');
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

            getIntermediateShippingAddressFromGoogle(addr) {
                return {
                    "region": addr.administrativeArea,
                    "country_id": addr.countryCode,
                    "postcode": addr.postalCode,
                    "city": addr.locality,
                }
            },

            constructAddressInformationForGoogle(data, methods) {
                let billingAddress = {}
                if (data.paymentMethodData) {
                    let addr = data.paymentMethodData.info.billingAddress
                    let names = addr.name.split(' ')
                    billingAddress = {
                        countryId: addr.countryCode,
                        // "regionCode": "DC",
                        region: addr.administrativeArea,
                        street: [addr.address1 + addr.address2 + addr.address3],
                        telephone: addr.phoneNumber,
                        postcode: addr.postalCode,
                        city: addr.locality,
                        firstname: names[0],
                        lastname: names.length > 1 ? names[names.length - 1] : names[0],
                    }
                }

                let selectedMethod = methods.find(item => item.carrier_code === data.shippingOptionData.id) || methods[0];

                let firstname = '', lastname = ''
                if (data.shippingAddress && data.shippingAddress.name) {
                    let names = data.shippingAddress.name.split(' ') || [];
                    firstname = data.shippingAddress.name ? names[0] : '';
                    lastname = names.length > 1 ? names[names.length - 1] : firstname;
                }

                let information = {
                    "addressInformation": {
                        "shipping_address": {
                            "countryId": data.shippingAddress.countryCode,
                            "region": data.shippingAddress.administrativeArea,
                            "street": [data.shippingAddress.address1 + data.shippingAddress.address2 + data.shippingAddress.address3],
                            "telephone": data.shippingAddress.phoneNumber,
                            "postcode": data.shippingAddress.postalCode,
                            "city": data.shippingAddress.locality,
                            firstname,
                            lastname,
                        },
                        "billing_address": billingAddress,
                        "shipping_method_code": selectedMethod.method_code,
                        "shipping_carrier_code": selectedMethod.carrier_code,
                        "extension_attributes": {}
                    }
                }
                return {information, selectedMethod}
            },

            getBillingAddressToPlaceOrder() {
                return {
                    "countryId": this.billingAddress.address.country_code,
                    "regionCode": this.billingAddress.address.state,
                    "street": [
                        this.billingAddress.address.street[0],
                    ],
                    "telephone": this.billingAddress.telephone,
                    "postcode": this.billingAddress.address.postcode,
                    "city": this.billingAddress.address.city,
                    "firstname": this.billingAddress.first_name,
                    "lastname": this.billingAddress.last_name,
                }
            },

            setBillingAddressFromGoogle(data) {
                let addr = data.paymentMethodData.info.billingAddress
                let names = addr.name.split(' ')
                this.billingAddress = {
                    address: {
                        city: addr.locality,
                        country_code: addr.countryCode,
                        postcode: addr.postalCode,
                        state: addr.administrativeArea,
                        street: [addr.address1 + addr.address2 + addr.address3],
                    },
                    first_name: names[0],
                    last_name: names.length > 1 ? names[names.length - 1] : names[0],
                    email: data.email,
                    telephone: addr.phoneNumber
                }
            },

            setBillingAddressFromOfficial() {
                const billingAddress = quote.billingAddress();

                this.billingAddress = {
                    address: {
                        city: billingAddress.city,
                        country_code: billingAddress.countryId,
                        postcode: billingAddress.postcode,
                        state: billingAddress.region,
                        street: billingAddress.street
                    },
                    first_name: billingAddress.firstname,
                    last_name: billingAddress.lastname,
                    email: this.quoteRemote.email,
                    telephone: billingAddress.telephone
                }
            },
        });
    }
);
