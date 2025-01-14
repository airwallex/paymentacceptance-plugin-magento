define(
    [
        'jquery',
        'ko',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'uiComponent',
        'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
        'Airwallex_Payments/js/view/payment/utils',
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
                amount: 0
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
                if (obj.type && obj.type === 'error') {
                    throw new Error(obj.message);
                }

                this.updateExpressData(obj.quote_data);
                this.updateMethods(obj.methods, obj.selected_method);
                addressHandler.regionId = obj.region_id || 0;
                return obj;
            },

            updateExpressData(expressData) {
                this.expressDataObjects().forEach(o => {
                    Object.assign(o.expressData, expressData);
                });
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
                this.paymentConfig.express_button_sort.sort().forEach(v => {
                    this.buttonSort.push(v);
                });

                Airwallex.init({
                    env: this.paymentConfig.mode,
                    origin: window.location.origin,
                });

                this.isShow(true);
            },

            async loadPayment() {
                this.initMinicartClickEvents();
                utils.initProductPageFormClickEvents();
                this.initHashPaymentEvent();
                utils.initCheckoutPageExpressCheckoutClick();

                if (this.from === 'minicart' && utils.isCartEmpty(this.expressData)) {
                    return;
                }
                this.createPays();

                if (utils.isCheckoutPage()) {
                    let quote = require('Magento_Checkout/js/model/quote');
                    quote.totals.subscribe(async (newValue) => {
                        let old = this.amount;
                        this.amount = newValue.grand_total;
                        if (!old) {
                            return;
                        }

                        if (Math.abs(old - this.amount) >= 0.0001) {
                            $('body').trigger('processStart');
                            this.destroyElement();
                            await this.fetchExpressData();
                            this.createPays();
                            $('body').trigger('processStop');
                        }
                    });
                }
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
                if (this.isGooglePayActive()) {
                    Airwallex.destroyElement('googlePayButton');
                }
                if (this.isApplePayActive()) {
                    Airwallex.destroyElement('applePayButton');
                }
            },

            isGooglePayActive() {
                return this.paymentConfig.checkout.indexOf('google_pay') !== -1;
            },

            isApplePayActive() {
                return this.paymentConfig.checkout.indexOf('apple_pay') !== -1;
            },

            deviceSupportApplePay() {
                const APPLE_PAY_VERSION = 4;
                return 'ApplePaySession' in window && ApplePaySession.supportsVersion && ApplePaySession.canMakePayments &&
                    ApplePaySession.supportsVersion(APPLE_PAY_VERSION) &&
                    ApplePaySession.canMakePayments()
            },

            createPays() {
                if (this.isGooglePayActive()) {
                    googlepay.create(this);
                }
                if (this.isApplePayActive()) {
                    applepay.create(this);
                }
            },

            async validateAddresses() {
                let url = urlBuilder.build('rest/V1/airwallex/payments/validate-addresses');
                return storage.get(url, undefined, 'application/json', {});
            },

            placeOrder(pay) {
                $('body').trigger('processStart');
                const payload = {
                    cartId: utils.getCartId(),
                    paymentMethod: {
                        method: this.code,
                        additional_data: {}
                    },
                    from: pay
                };

                (new Promise(async (resolve, reject) => {
                    try {
                        let resp = await this.validateAddresses();
                        let obj = JSON.parse(resp);
                        if (obj.type && obj.type === 'error') {
                            throw new Error(obj.message);
                        }

                        let id = utils.isRecaptchaShared() ? utils.recaptchaId : utils.expressRecaptchaId;
                        await utils.setRecaptchaToken(payload, id);

                        if (!utils.isLoggedIn()) {
                            payload.email = utils.isCheckoutPage() ? $(utils.guestEmailSelector).val() : this.guestEmail;
                            if (!payload.email) {
                                throw new Error('Email is required!');
                            }
                        }

                        // if (!utils.isCheckoutPage()) {
                        if (!payload.paymentMethod.extension_attributes) {
                            payload.paymentMethod.extension_attributes = {};
                        }
                        payload.paymentMethod.extension_attributes.agreement_ids = [];
                        let agreements = this.expressData.settings.agreements;
                        if (agreements && agreements.checkoutAgreements && agreements.checkoutAgreements.agreements && agreements.checkoutAgreements.agreements.length) {
                            for (let item of agreements.checkoutAgreements.agreements) {
                                payload.paymentMethod.extension_attributes.agreement_ids.push(item.agreementId);
                            }
                        }
                        // }

                        await utils.postPaymentInformation(payload, utils.isLoggedIn(), utils.getCartId());

                        let intentResponse = await utils.getIntent(payload, {});
                        if (!intentResponse) return;

                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;

                        try {
                            await eval(pay).confirmIntent(params);
                        } catch (error) {
                            if (error.code !== 'invalid_status_for_operation') {
                                throw error;
                            }
                        }

                        let endResult = await utils.placeOrder(payload, intentResponse, {});

                        resolve(endResult);
                    } catch (e) {
                        this.destroyElement();
                        this.createPays();
                        reject(e);
                    }
                })).then(response => {
                    utils.clearDataAfterPay(response, customerData);

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
