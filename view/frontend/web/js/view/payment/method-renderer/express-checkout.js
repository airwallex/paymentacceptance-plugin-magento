define(
    [
        'jquery',
        'ko',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'uiComponent',
        'Airwallex_Payments/js/view/payment/method-renderer/card-method-recaptcha', //
        'Magento_Customer/js/model/authentication-popup',
        'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
        'Airwallex_Payments/js/view/payment/method-renderer/express/utils',
    ],

    function (
        $,
        ko,
        storage,
        customerData,
        urlBuilder,
        Component,
        cardMethodRecaptcha,
        popup,
        addressHandler,
        utils,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                code: 'airwallex_payments_express',
                paymentConfig: {},
                recaptcha: null,
                googlepay: null,
                expressData: {},
                guestEmail: "",
                billingAddress: {},
                shippingMethods: [],
                methods: [],
                showMinicartSelector: '.showcart',
                isPaymentLoaded: false,
                isShow: ko.observable(false),
                isActive: ko.observable(false),
                expressDisplayArea: ko.observable(''), // displayArea is a key word
                utils: utils,
                buttonSort: ko.observable([])
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

            getGooglePayRequestOptions() {
                let paymentDataRequest = this.getOptions()
                paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
                if (utils.isRequireShippingAddress()) {
                    paymentDataRequest.callbackIntents.push('SHIPPING_ADDRESS');
                    paymentDataRequest.shippingAddressRequired = true;
                    paymentDataRequest.shippingAddressParameters = {
                        phoneNumberRequired: this.paymentConfig.is_express_phone_required,
                    };
                }

                if (utils.isRequireShippingOption()) {
                    paymentDataRequest.callbackIntents.push('SHIPPING_OPTION');
                    paymentDataRequest.shippingOptionRequired = true;
                }

                const transactionInfo = {
                    amount: {
                        value: utils.formatCurrency(this.expressData.grand_total),
                        currency: $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code,
                    },
                    countryCode: this.paymentConfig.country_code,
                    displayItems: this.getDisplayItems(),
                };

                return Object.assign(paymentDataRequest, transactionInfo);
            },

            getDisplayItems() {
                let res = [];
                for (let key in this.expressData) {
                    if (this.expressData[key] === '0.0000' || !this.expressData[key]) {
                        continue
                    }
                    if (key === 'shipping_amount') {
                        res.push({
                            'label': 'Shipping',
                            'type': 'LINE_ITEM',
                            'price': utils.formatCurrency(this.expressData[key])
                        })
                    } else if (key === 'tax_amount') {
                        res.push({
                            'label': 'Tax',
                            'type': 'TAX',
                            'price': utils.formatCurrency(this.expressData[key])
                        })
                    } else if (key === 'subtotal') {
                        res.push({
                            'label': 'Subtotal',
                            'type': 'SUBTOTAL',
                            'price': utils.formatCurrency(this.expressData[key])
                        })
                    } else if (key === 'subtotal_with_discount') {
                        if (this.expressData[key] !== this.expressData['subtotal']) {
                            res.push({
                                'label': 'Discount',
                                'type': 'LINE_ITEM',
                                'price': '-' + utils.getDiscount(this.expressData['subtotal'], this.expressData['subtotal_with_discount']).toString()
                            })
                        }
                    }
                }
                return res
            },

            setGuestEmail(email) {
                this.guestEmail = email
            },

            async fetchExpressData() {
                let url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
                if (utils.isProductPage()) {
                    url += "?is_product_page=1&product_id=" + $("input[name=product]").val()
                }
                const resp = await storage.get(url, undefined, 'application/json', {});
                let obj = JSON.parse(resp)
                this.expressData = obj
                this.paymentConfig = Object.assign(this.paymentConfig, obj.settings)
                utils.expressData = obj
                utils.paymentConfig = this.paymentConfig
            },

            initMinicartClickEvents() {
                if (!$(this.showMinicartSelector).length) {
                    return
                }

                let recreateGooglepay = async () => {
                    if (!$(this.showMinicartSelector).hasClass('active')) {
                        return
                    }
                    Airwallex.destroyElement('googlePayButton');
                    await this.fetchExpressData();
                    if (this.from === 'minicart' && utils.isCartEmpty(this.expressData)) {
                        return
                    }
                    this.createGooglepay()
                }

                let cartData = customerData.get('cart')
                cartData.subscribe(recreateGooglepay, this);

                if (this.from !== 'minicart' || utils.isFromMinicartAndShouldNotShow(this.from)) {
                    return
                }
                $(this.showMinicartSelector).on("click", recreateGooglepay)
            },

            async initialize() {
                this._super();

                await this.fetchExpressData()
                this.buttonSort(this.paymentConfig.express_button_sort)

                this.isActive(this.paymentConfig.is_express_active)
                if (!this.paymentConfig.is_express_active) {
                    return
                }

                this.expressDisplayArea(this.paymentConfig.display_area)
                if (this.paymentConfig.display_area.indexOf(this.from) === -1) {
                    return
                }
                if (utils.isFromMinicartAndShouldNotShow(this.from)) {
                    return
                }

                await this.loadPayment()
            },

            async loadPayment() {
                // only apply in minicart
                if (this.from === 'minicart') {
                    this.isShow(true)
                }

                this.initMinicartClickEvents()
                utils.initProductPageFormClickEvents()


                this.recaptcha = cardMethodRecaptcha();
                // this.recaptcha.renderReCaptcha();

                Airwallex.init({
                    env: this.paymentConfig.mode,
                    origin: window.location.origin,
                });

                if (this.from === 'minicart' && utils.isCartEmpty(this.expressData)) {
                    return
                }
                this.createGooglepay()

                window.addEventListener('hashchange', async () => {
                    if (window.location.hash === '#payment') {
                        Airwallex.destroyElement('googlePayButton');
                        // we need update quote, because we choose shipping method last step
                        await this.fetchExpressData();
                        this.createGooglepay()
                    }
                });
            },

            createGooglepay() {
                this.googlepay = Airwallex.createElement('googlePayButton', this.getGooglePayRequestOptions())
                let mountId = this.from === 'minicart' ? 'awx-google-pay-minicart' : 'awx-google-pay'
                this.googlepay.mount(mountId);
                this.attachEventsToGooglepay()
            },

            attachEventsToGooglepay() {
                let updateQuoteByShipment = async (event) => {
                    if (utils.isProductPage() && utils.isSetActiveInProductPage()) {
                        try {
                            let res = await $.ajax(utils.addToCartOptions())
                            Object.assign(this.expressData, JSON.parse(res))
                        } catch (res) {
                            utils.error(res)
                        }
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart'], true);
                    }
                    // 1. estimateShippingMethods
                    if (utils.isRequireShippingAddress()) {
                        let addr = addressHandler.getIntermediateShippingAddressFromGoogle(event.detail.intermediatePaymentData.shippingAddress)
                        this.methods = await addressHandler.estimateShippingMethods(addr, utils.isLoggedIn(), utils.getCartId())
                    }

                    // 2. postShippingInformation
                    let {information, selectedMethod} = addressHandler.constructAddressInformationFromGoogle(
                        utils.isRequireShippingAddress(), event.detail.intermediatePaymentData, this.methods
                    )
                    await addressHandler.postShippingInformation(information, utils.isLoggedIn(), utils.getCartId())

                    // 3. update quote
                    await this.fetchExpressData()

                    let options = this.getGooglePayRequestOptions();
                    if (utils.isRequireShippingOption()) {
                        options.shippingOptionParameters = addressHandler.formatShippingMethodsToGoogle(this.methods, selectedMethod)
                    }
                    this.googlepay.update(options);
                }

                this.googlepay.on('shippingAddressChange', updateQuoteByShipment);

                this.googlepay.on('shippingMethodChange', updateQuoteByShipment);

                this.googlepay.on('authorized', async (event) => {
                    this.setGuestEmail(event.detail.paymentData.email)

                    if (utils.isRequireShippingAddress()) {
                        // this time google provide full shipping address, we should post to magento
                        let {information} = addressHandler.constructAddressInformationFromGoogle(
                            utils.isRequireShippingAddress(), event.detail.paymentData, this.methods
                        )
                        await addressHandler.postShippingInformation(information, utils.isLoggedIn(), utils.getCartId())
                    } else {
                        await addressHandler.postBillingAddress({
                            'cartId': utils.getCartId(),
                            'address': addressHandler.getBillingAddressFromGoogle(event.detail.paymentData.paymentMethodData.info.billingAddress)
                        }, utils.isLoggedIn(), utils.getCartId())
                    }
                    this.billingAddress = addressHandler.setIntentConfirmBillingAddressFromGoogle(event.detail.paymentData);
                    this.placeOrder()
                });
            },

            placeOrder() {
                $('body').trigger('processStart');
                const payload = {
                    cartId: utils.getCartId(),
                    paymentMethod: {
                        method: this.code,
                        additional_data: {
                            amount: 0,
                            intent_status: 0
                        }
                    }
                };

                let serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order')
                if (utils.isLoggedIn()) {
                    serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
                }

                payload.intent_id = null;

                (new Promise(async (resolve, reject) => {
                    try {
                        payload.xReCaptchaValue = await (new Promise((resolve) => {
                            this.getRecaptchaToken(resolve);
                        }));

                        if (!utils.isLoggedIn()) {
                            payload.email = utils.isCheckoutPage() ? $("#customer-email").val() : this.guestEmail;
                        }

                        const intentResponse = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                        );

                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;
                        params.payment_method = {};
                        params.payment_method.billing = this.billingAddress;

                        payload.intent_id = intentResponse.intent_id;
                        payload.xReCaptchaValue = null;
                        if (utils.isRequireShippingOption()) {
                            payload.billingAddress = addressHandler.getBillingAddressToPlaceOrder(this.billingAddress)
                        }
                        await this.googlepay.confirmIntent(params);

                        const endResult = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                        );
                        resolve(endResult);
                    } catch (e) {
                        reject(e);
                    }
                })).then(response => {
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

                    window.location.replace(urlBuilder.build('checkout/onepage/success/'));
                }).catch(
                    utils.error.bind(utils)
                ).finally(() => {
                    this.recaptcha.reset();
                    setTimeout(() => {
                        $('body').trigger('processStop')
                    }, 3000)
                });
            },

            getRecaptchaToken(callback) {
                if (!this.isRecaptchaEnabled) {
                    return callback();
                }

                const reCaptchaId = this.recaptcha.getReCaptchaId()
                const registry = this.recaptcha.getRegistry();

                if (registry.tokens.hasOwnProperty(reCaptchaId)) {
                    const response = registry.tokens[reCaptchaId];
                    if (typeof response === 'object' && typeof response.then === 'function') {
                        response.then(token => {
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
        });
    }
);
