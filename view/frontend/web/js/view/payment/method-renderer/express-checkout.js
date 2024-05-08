define(
    [
        'jquery',
        'ko',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'uiComponent',
        'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
        'Airwallex_Payments/js/view/payment/method-renderer/express/utils',
        'Airwallex_Payments/js/view/payment/method-renderer/express/googlepay',
        'Airwallex_Payments/js/view/payment/method-renderer/express/applepay',
    ],

    function (
        $,
        ko,
        storage,
        customerData,
        urlBuilder,
        Component,
        addressHandler,
        utils,
        googlepay,
        applepay,
    ) {
        'use strict';

        return Component.extend({
            code: 'airwallex_payments_express',
            defaults: {
                paymentConfig: {},
                expressData: {},
                showMinicartSelector: '.showcart',
                isShow: false,
                buttonSort: ko.observableArray([]),
                isShowRecaptcha: ko.observable(false),
                guestEmail: "",
            },

            setGuestEmail(email) {
                this.guestEmail = email;
            },

            expressDataObjects() {
                return [this, utils, applepay, googlepay];
            },

            methodsObjects() {
                return [addressHandler, applepay, googlepay];

            },

            async fetchExpressData() {
                let url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
                if (utils.isProductPage()) {
                    url += "?is_product_page=1&product_id=" + $("input[name=product]").val();
                }
                const resp = await storage.get(url, undefined, 'application/json', {});
                let obj = JSON.parse(resp);
                this.updateExpressData(obj);
                this.updatePaymentConfig(obj.settings);
            },

            async postAddress(address, methodId = "") {
                let url = urlBuilder.build('rest/V1/airwallex/payments/post-address');
                let postOptions = utils.postOptions(address, url);
                if (methodId) {
                    postOptions.data.append('methodId', methodId);
                }
                let resp = await $.ajax(postOptions);

                let obj = JSON.parse(resp);
                this.updateExpressData(obj.quote_data);
                this.updateMethods(obj.methods, obj.selected_method);
                addressHandler.regionId = obj.region_id || 0;
                return obj;
            },

            updateExpressData(expressData) {
                this.expressDataObjects().forEach(o => {
                    Object.assign(o.expressData, expressData);
                });
                utils.toggleMaskFormLogin();
            },

            updatePaymentConfig(paymentConfig) {
                this.expressDataObjects().forEach(o => {
                    o.paymentConfig = paymentConfig;
                });
            },

            updateMethods(methods, selectedMethod) {
                this.methodsObjects().forEach(o => {
                    o.methods = methods;
                    o.selectedMethod = selectedMethod;
                });
            },

            initMinicartClickEvents() {
                if (!$(this.showMinicartSelector).length) {
                    return;
                }

                let recreatePays = async () => {
                    if (!$(this.showMinicartSelector).hasClass('active')) {
                        return;
                    }

                    this.destroyElement();
                    await this.fetchExpressData();
                    if (this.from === 'minicart' && utils.isCartEmpty(this.expressData)) {
                        return;
                    }
                    this.createPays();
                };

                let cartData = customerData.get('cart');
                cartData.subscribe(recreatePays, this);

                if (this.from !== 'minicart' || utils.isFromMinicartAndShouldNotShow(this.from)) {
                    return;
                }
                $(this.showMinicartSelector).on("click", recreatePays);
            },

            async initialize() {
                this._super();

                this.isShow = ko.observable(false);

                await this.fetchExpressData();

                if (!this.paymentConfig.is_express_active || this.paymentConfig.display_area.indexOf(this.from) === -1) {
                    return;
                }

                if (utils.isFromMinicartAndShouldNotShow(this.from)) {
                    return;
                }

                googlepay.from = this.from;
                applepay.from = this.from;
                this.paymentConfig.express_button_sort.forEach(v => {
                    this.buttonSort.push(v);
                });

                Airwallex.init({
                    env: this.paymentConfig.mode,
                    origin: window.location.origin,
                });

                this.isShow(true);
            },

            async loadPayment() {
                utils.toggleMaskFormLogin();
                this.initMinicartClickEvents();
                utils.initProductPageFormClickEvents();
                this.initHashPaymentEvent();
                utils.initCheckoutPageExpressCheckoutClick();

                if (this.from === 'minicart' && utils.isCartEmpty(this.expressData)) {
                    return;
                }
                this.createPays();
            },

            initHashPaymentEvent() {
                window.addEventListener('hashchange', async () => {
                    if (window.location.hash === '#payment') {
                        this.destroyElement();
                        // we need update quote, because we choose shipping method last step
                        await this.fetchExpressData();
                        this.createPays();
                    }
                });
            },

            destroyElement() {
                Airwallex.destroyElement('googlePayButton');
                Airwallex.destroyElement('applePayButton');
            },

            createPays() {
                googlepay.create(this);
                // applepay.create(this);
            },

            async validateAddresses() {
                let url = urlBuilder.build('rest/V1/airwallex/payments/validate-addresses');
                await storage.get(url, undefined, 'application/json', {});
            },

            placeOrder(pay) {
                $('body').trigger('processStart');
                const payload = {
                    cartId: utils.getCartId(),
                    paymentMethod: {
                        method: this.code
                    }
                };

                let serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order');
                if (utils.isLoggedIn()) {
                    serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
                }

                payload.intent_id = null;

                (new Promise(async (resolve, reject) => {
                    try {
                        await this.validateAddresses();
                        
                        if (this.paymentConfig.is_recaptcha_enabled) {
                            payload.xReCaptchaValue = await utils.recaptchaToken();
                        }

                        if (!utils.isLoggedIn()) {
                            payload.email = utils.isCheckoutPage() ? $(utils.guestEmailSelector).val() : this.guestEmail;
                            if (!payload.email) {
                                throw new Error('Email is required!');
                            }
                        }

                        const intentResponse = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                        );

                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;
                        params.payment_method = {};
                        params.payment_method.billing = addressHandler.intentConfirmBillingAddressFromGoogle;
                        if (utils.isCheckoutPage()) {
                            addressHandler.setIntentConfirmBillingAddressFromOfficial(this.expressData.billing_address);
                            params.payment_method.billing = addressHandler.intentConfirmBillingAddressFromOfficial;
                        }

                        await eval(pay).confirmIntent(params);

                        payload.intent_id = intentResponse.intent_id;
                        const endResult = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                        );

                        resolve(endResult);
                    } catch (e) {
                        this.destroyElement();
                        this.createPays();
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
                    setTimeout(() => {
                        $('body').trigger('processStop');
                    }, 3000);
                });
            },
        });
    }
);
