define([
    'mage/url',
    'jquery',
    'mage/storage',
    'Magento_Customer/js/model/authentication-popup',
    'Magento_Ui/js/modal/modal',
    'Airwallex_Payments/js/view/payment/recaptcha/webapiReCaptcha',
    'Airwallex_Payments/js/view/payment/recaptcha/webapiReCaptchaRegistry',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/alert',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment/place-order-hooks',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_CheckoutAgreements/js/model/agreement-validator',
    'Magento_Checkout/js/model/error-processor'
], function (
    urlBuilder,
    $,
    storage,
    popup,
    modal,
    webapiReCaptcha,
    webapiRecaptchaRegistry,
    customerData,
    alert,
    customer,
    placeOrderHooks,
    addressHandler,
    agreementValidator,
    errorProcessor
) {
    'use strict';

    return {
        productFormSelector: "#product_addtocart_form",
        guestEmailSelector: "#customer-email",
        cartPageIdentitySelector: '.cart-summary',
        checkoutPageIdentitySelector: '#co-payment-form',
        buttonMaskSelector: '.aws-button-mask',
        buttonMaskAgreementSelector: '.aws-button-mask-for-agreement',
        buttonMaskSelectorForLogin: '.aws-button-mask-for-login',
        expressData: {},
        paymentConfig: {},
        recaptchaSelector: '.airwallex-recaptcha',
        recaptchaId: 'recaptcha-checkout-place-order',
        expressRecaptchaId: 'express-recaptcha-checkout-place-order',
        agreementSelector: '.airwallex-express-checkout .checkout-agreements input[type="checkbox"]',

        getRecaptchaId() {
            let id = $('.airwallex-card-container .g-recaptcha').attr('id');
            if (id) {
                return id;
            }
            if ($('#' + this.recaptchaId).length) {
                return this.recaptchaId;
            }
            return '';
        },

        clearDataAfterPay(response, customerData) {
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
        },

        getDiscount(subtotal, subtotal_with_discount) {
            let diff = subtotal - subtotal_with_discount;
            return diff.toFixed(2);
        },

        formatCurrency(v) {
            return parseFloat(v).toFixed(2);
        },

        isCartEmpty() {
            return !parseInt(this.expressData.items_qty);
        },

        isProductPage() {
            return !!$(this.productFormSelector).length;
        },

        isCartPage() {
            return !!$(this.cartPageIdentitySelector).length;
        },

        isCheckoutPage() {
            return !!$(this.checkoutPageIdentitySelector).length;
        },

        checkProductForm() {
            let formSelector = $(this.productFormSelector);
            if (formSelector.length === 0 || !formSelector.validate) {
                return true;
            }
            return $(formSelector).validate().checkForm();
        },

        validateProductOptions() {
            if (this.checkProductForm()) {
                $(this.productFormSelector).valid();
                $(this.buttonMaskSelector).hide();
            } else {
                $(this.buttonMaskSelector).show();
            }
        },

        showLoginForm(e) {
            e.preventDefault();
            popup.showModal();
            if (popup.modalWindow) {
                popup.showModal();
            } else {
                alert({
                    content: $.mage.__('Guest checkout is disabled.')
                });
            }
        },

        validateAgreements: function (selector) {
            var checkoutConfig = window.checkoutConfig,
                agreementsConfig = checkoutConfig ? checkoutConfig.checkoutAgreements : {};

            var isValid = true;

            if (!agreementsConfig.isEnabled || $(selector).length === 0) {
                return true;
            }

            $(selector).each(function (index, element) {
                if (!$.validator.validateSingleElement(element, {
                    errorElement: 'div',
                    hideError: false
                })) {
                    isValid = false;
                }
            });

            return isValid;
        },

        checkAgreements() {
            if (this.allAgreementsCheck()) {
                $(this.buttonMaskAgreementSelector).hide();
            } else {
                $(this.buttonMaskAgreementSelector).show();
            }
        },

        allAgreementsCheck() {
            let status = true;
            $(this.agreementSelector).each(function () {
                if (!this.checked) {
                    status = false;
                    return false;
                }
            });
            return status;
        },

        initCheckoutPageExpressCheckoutAgreement() {
            if (this.isCheckoutPage()) {
                let agreementsConfig = window.checkoutConfig.checkoutAgreements || {};
                if (!agreementsConfig.isEnabled || $(this.agreementSelector).length === 0) {
                    $(this.buttonMaskAgreementSelector).hide();
                    return;
                }
                this.checkAgreements();
                $(this.agreementSelector).off('change.awx').on('change.awx', () => {
                    this.checkAgreements();
                });
                $(this.buttonMaskAgreementSelector).off('click.awx').on('click.awx', (e) => {
                    e.stopPropagation();
                    this.checkAgreements();
                    this.validateAgreements(this.agreementSelector);
                });
            }
        },

        initCheckoutPageExpressCheckoutClick() {
            this.initCheckoutPageExpressCheckoutAgreement();
            if (this.isCheckoutPage() && !this.isLoggedIn() && this.expressData.is_virtual) {
                this.checkGuestEmailInput();
                $(this.guestEmailSelector).off('input.awx').on('input.awx', () => {
                    this.checkGuestEmailInput();
                });
                $(this.buttonMaskSelector).off('click.awx').on('click.awx', (e) => {
                    e.stopPropagation();
                    $($(this.guestEmailSelector).closest('form')).valid();
                    this.checkGuestEmailInput();
                });
            }
        },

        checkGuestEmailInput() {
            if ($(this.guestEmailSelector).closest('form').validate().checkForm()) {
                $(this.buttonMaskSelector).hide();
            } else {
                $(this.buttonMaskSelector).show();
            }
        },

        initProductPageFormClickEvents() {
            if (this.isProductPage() && this.isSetActiveInProductPage()) {
                this.validateProductOptions();
                $(this.productFormSelector).on("click", () => {
                    this.validateProductOptions();
                });
                $(this.buttonMaskSelector).on('click', (e) => {
                    e.stopPropagation();
                    $(this.productFormSelector).valid();
                    this.validateProductOptions();
                });
                $.each($(this.productFormSelector)[0].elements, (index, element) => {
                    $(element).on('change', () => {
                        this.validateProductOptions();
                    });
                });
            }
        },

        loadRecaptcha(isShowRecaptcha) {
            if (!$(this.recaptchaSelector).length) {
                return;
            }

            if (this.paymentConfig.is_recaptcha_enabled && !$('#' + this.expressRecaptchaId).length) {
                window.isShowAwxGrecaptcha = true;
                isShowRecaptcha(true);
                let re = webapiReCaptcha();
                re.reCaptchaId = this.expressRecaptchaId;
                re.settings = this.paymentConfig.recaptcha_settings;
                re.renderReCaptcha();
                $(this.recaptchaSelector).css({
                    'visibility': 'hidden',
                    'position': 'absolute'
                });
            }
        },

        isSetActiveInProductPage() {
            return this.paymentConfig.display_area.indexOf('product_page') !== -1;
        },

        isSetActiveInCartPage() {
            return this.paymentConfig.display_area.indexOf('cart_page') !== -1;
        },

        isFromMinicartAndShouldNotShow(from) {
            if (from !== 'minicart') {
                return false;
            }
            if (this.isProductPage() && this.isSetActiveInProductPage()) {
                return true;
            }
            return this.isCartPage() && this.isSetActiveInCartPage();
        },

        isRequireShippingOption() {
            if (this.isProductPage()) {
                if (this.isCartEmpty()) {
                    return !this.expressData.product_is_virtual;
                }
                return !this.expressData.is_virtual || !this.expressData.product_is_virtual;
            }
            return this.isRequireShippingAddress();
        },

        async getSavedCards() {
            let url = urlBuilder.build('rest/V1/airwallex/saved_cards');
            return await storage.get(url, undefined, 'application/json', {});
        },

        async getRegionId(country, region) {
            let url = urlBuilder.build('rest/V1/airwallex/region_id?country=' + country + '&region=' + region);
            return await storage.get(url, undefined, 'application/json', {});
        },

        isRequireShippingAddress() {
            if (this.isProductPage()) {
                return true;
            }
            if (this.isCheckoutPage()) {
                return false;
            }
            return !this.expressData.is_virtual;
        },

        postOptions(data, url) {
            let formData = new FormData();
            if (Array.isArray(data)) {
                $.each(data, function (index, field) {
                    formData.append(field.name, field.value);
                });
            } else {
                for (let k in data) {
                    formData.append(k, data[k]);
                }
            }

            return {
                url,
                data: formData,
                processData: false,
                contentType: false,
                type: 'POST',
            };
        },

        addToCartOptions() {
            let arr = $(this.productFormSelector).serializeArray();
            let url = urlBuilder.build('rest/V1/airwallex/payments/add-to-cart');
            return this.postOptions(arr, url);
        },

        async addToCart(that) {
            if (this.isProductPage() && this.isSetActiveInProductPage()) {
                try {
                    let res = await $.ajax(this.addToCartOptions());
                    that.updateExpressData(JSON.parse(res));
                } catch (res) {
                    this.error(res);
                }
                customerData.invalidate(['cart']);
                customerData.reload(['cart'], true);
            }
        },

        getCartId() {
            return this.isLoggedIn() ? this.expressData.cart_id : this.expressData.mask_cart_id;
        },

        error(response) {
            let modalSelector = $('#awx-modal');
            modal({title: 'Error'}, modalSelector);

            $('body').trigger('processStop');
            let errorMessage = $.mage.__(response.message);
            if (response.responseText) {
                errorMessage = $.mage.__(response.responseText);
            }
            if (response.responseJSON) {
                errorMessage = $.mage.__(response.responseJSON.message);
            }

            $("#awx-modal .modal-body-content").html(errorMessage);
            modalSelector.modal('openModal');
        },

        redirectToSuccess() {
            window.location.replace(urlBuilder.build('checkout/onepage/success/'));
        },

        processPlaceOrderError: function (response) {
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

            $('body').trigger('processStop');
        },

        isLoggedIn() {
            if (window.checkoutConfig) {
                return customer.isLoggedIn();
            }
            return !!this.expressData.customer_id;
        },

        placeOrderUrl() {
            let serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order');
            if (this.isLoggedIn()) {
                serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
            }
            return serviceUrl;
        },

        async getIntent(payload, headers = {}) {
            if (!this.isLoggedIn()) {
                if (!payload.email) {
                    throw new Error('Email is required!');
                }
            }
            if (!payload.paymentMethod || !payload.paymentMethod.method || !payload.paymentMethod.additional_data) {
                throw new Error('Payment method is required!');
            }
            if (!payload.cartId) {
                throw new Error('Cart ID is required!');
            }
            let intentResponse = {};
            try {
                intentResponse = await storage.post(
                    this.placeOrderUrl(), JSON.stringify(payload), true, 'application/json', headers
                );
            } catch (e) {
                if (e.status === 404) {
                    this.clearDataAfterPay({}, customerData);
                    // this.redirectToSuccess();
                    // return;
                }
                throw e;
            }
            if (intentResponse.response_type === 'error') {
                throw new Error(intentResponse.message);
            }
            return intentResponse;
        },

        async placeOrder(payload, intentResponse, headers = {}) {
            payload.intent_id = intentResponse.intent_id;
            payload.paymentMethod.additional_data.intent_id = intentResponse.intent_id;

            let endResult = {};
            try {
                endResult = await storage.post(
                    this.placeOrderUrl(), JSON.stringify(payload), true, 'application/json', headers
                );
            } catch (e) {
                if (e.status === 404) {
                    this.clearDataAfterPay({}, customerData);
                    this.redirectToSuccess();
                    return;
                }
                throw e;
            }

            if (endResult.response_type === 'error') {
                throw new Error(endResult.message);
            }
            return endResult;
        },

        dealConfirmException(error) {
            if (error.code !== 'invalid_status_for_operation') {
                throw error;
            }
        },

        async getRecaptchaToken(id) {
            return await new Promise((resolve, reject) => {
                webapiRecaptchaRegistry.tokens = {};
                webapiRecaptchaRegistry.addListener(id, (token) => {
                    resolve(token);
                });
                webapiRecaptchaRegistry.triggers[id]();
            });
        },

        getAgreementIds() {
            let agreementForm = $('.payment-method._active div[data-role=checkout-agreements] input');
            let agreementData = agreementForm.serializeArray();
            let agreementIds = [];

            agreementData.forEach(function (item) {
                agreementIds.push(item.value);
            });
            return agreementIds;
        },

        postPaymentInformation(payload, isLoggedIn, cartId) {
            let url = 'rest/V1/carts/mine/set-payment-information';
            if (!isLoggedIn) {
                url = 'rest/V1/guest-carts/' + cartId + '/set-payment-information';
            }
            return storage.post(
                urlBuilder.build(url), JSON.stringify(payload), undefined, 'application/json', {}
            );
        },

        async pay(self, from, quote) {
            let that = this;
            $('body').trigger('processStart');

            let cartId = quote.getQuoteId();
            const payload = {
                cartId: cartId,
                from: from,
                paymentMethod: {
                    method: 'airwallex_payments_card',
                    additional_data: {},
                    extension_attributes: {
                        'agreement_ids': this.getAgreementIds()
                    },
                },
            };

            if (from !== 'vault') {
                payload.billingAddress = quote.billingAddress();
            }

            if (!this.isLoggedIn()) {
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
                                recaptchaRegistry.tokens = {};
                                recaptchaRegistry.addListener(that.getRecaptchaId(), (token) => {
                                    resolve(token);
                                });
                                recaptchaRegistry.triggers[that.getRecaptchaId()]();
                            });
                        }
                        // payload.xReCaptchaValue = await that.getRecaptchaToken(that.getRecaptchaId());
                    }

                    let paymentMethodId = '';
                    if (from === 'card') {
                        if (!quote.guestEmail) {
                            throw new Error('Email address is required.')
                        }
                        if (!quote.billingAddress()) {
                            throw new Error('Billing address is required.')
                        }
                        let clientSecret, customerId;
                        if (self.getCustomerId()) {
                            let requestUrl = urlBuilder.build('rest/V1/airwallex/generate_client_secret');
                            let res = await storage.get(requestUrl, undefined, 'application/json', {});
                            clientSecret = res.client_secret;
                            customerId = self.getCustomerId();
                        } else {
                            let requestUrl = urlBuilder.build('rest/V1/airwallex/guest/generate_client_secret');
                            let res = await storage.get(requestUrl, undefined, 'application/json', {});
                            clientSecret = res.client_secret;
                            customerId = res.customer_id;
                        }
                        try {
                            let res = await Airwallex.createPaymentMethod(clientSecret, {
                                element: self.cardNumberElement,
                                customer_id: customerId,
                            });
                            paymentMethodId = res.id;
                        } catch (err) {
                            console.log(err)
                            throw new Error($.mage.__('Invalid input. Please verify your payment details and try again.'));
                        }
                    } else if (from === 'vault') {
                        paymentMethodId = self.paymentMethodId();
                    }

                    payload.paymentMethodId = paymentMethodId;

                    let intentResponse = await that.getIntent(payload, headers);
                    if (!intentResponse) return;

                    let response = {};
                    try {
                        if (from === 'vault') {
                            const selectedConsentId = $("#v-" + $('input[name="payment[method]"]:checked').val()).val();
                            response = await Airwallex.confirmPaymentIntent({
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
                        } else {
                            if (self.isSaveCardSelected() && self.getCustomerId()) {
                                payload.from = 'card_with_saved';
                                response = await Airwallex.createPaymentConsent({
                                    intent_id: intentResponse.intent_id,
                                    customer_id: self.getCustomerId(),
                                    client_secret: intentResponse.client_secret,
                                    currency: quote.totals().quote_currency_code,
                                    billing: self.getBillingInformation(),
                                    element: self.cardNumberElement,
                                    next_triggered_by: 'customer',
                                });
                            } else {
                                response = await Airwallex.confirmPaymentIntent({
                                    intent_id: intentResponse.intent_id,
                                    client_secret: intentResponse.client_secret,
                                    payment_method: {
                                        billing: self.getBillingInformation()
                                    },
                                    element: self.cardNumberElement
                                });
                            }
                        }
                    } catch (error) {
                        that.dealConfirmException(error);
                    }
                    // 200 "status": "REQUIRES_CAPTURE",
                    // 400 code: "invalid_status_for_operation"
                    // if (from !== 'vault') {
                    //     payload.billingAddress = quote.billingAddress();
                    // }

                    // setTimeout(async () => {
                    //     let endResult = await that.placeOrder(payload, intentResponse, headers);
                    //     resolve(endResult);
                    // }, 20000);
                    let endResult = await that.placeOrder(payload, intentResponse, headers);
                    resolve(endResult);

                } catch (e) {
                    reject(e);
                }
            })).then(function (response) {
                that.clearDataAfterPay(response, customerData);
                that.redirectToSuccess();
                return;
            }).catch(
                that.processPlaceOrderError.bind(self)
            ).finally(
                function () {
                    _.each(placeOrderHooks.afterRequestListeners, function (listener) {
                        listener();
                    });

                    if (self.isPlaceOrderActionAllowed) {
                        self.isPlaceOrderActionAllowed(true);
                    }
                }
            );
        }
    };
});
