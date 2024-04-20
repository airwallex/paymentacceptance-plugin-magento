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
        'Airwallex_Payments/js/view/payment/method-renderer/express/googlepay',
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
        googlepay,
    ) {
        'use strict';

        return Component.extend({
            code: 'airwallex_payments_express',
            defaults: {
                paymentConfig: {},
                recaptcha: null,
                googlepay: null,
                expressData: {},
                guestEmail: "",
                billingAddress: {},
                showMinicartSelector: '.showcart',
                isShow: ko.observable(false),
                isActive: ko.observable(false),
                expressDisplayArea: ko.observable(''), // displayArea is a key word
                buttonSort: ko.observable([])
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
                googlepay.expressData = obj
                googlepay.paymentConfig = this.paymentConfig
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
                    googlepay.create(this)
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

                googlepay.from = this.from

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
                googlepay.create(this)

                window.addEventListener('hashchange', async () => {
                    if (window.location.hash === '#payment') {
                        Airwallex.destroyElement('googlePayButton');
                        // we need update quote, because we choose shipping method last step
                        await this.fetchExpressData();
                        googlepay.create(this)
                    }
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
                        await googlepay.confirmIntent(params);

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
