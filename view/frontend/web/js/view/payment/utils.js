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
    'Magento_ReCaptchaFrontendUi/js/registry'
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
    recaptchaRegistry
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
            if (this.isRecaptchaShared()) return this.recaptchaId;
            return $('.payment-method._active .g-recaptcha').attr('id') || '';
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
                // customerData.reload(['cart'], true);
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
            let checkoutConfig = window.checkoutConfig,
                agreementsConfig = checkoutConfig ? checkoutConfig.checkoutAgreements : {};

            let isValid = true;

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

        showAgreements() {
            if (!this.isCheckoutPage()) return;
            let agreementsConfig = window.checkoutConfig.checkoutAgreements || {};
            if (agreementsConfig.isEnabled && $(this.agreementSelector).length) {
                $(".airwallex-express-checkout .checkout-agreements").show();
                return;
            }
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
            if (this.isRecaptchaShared()) {
                return;
            }

            if (this.paymentConfig.is_recaptcha_enabled && !$('#' + this.expressRecaptchaId).length) {
                window.isShowAwxGrecaptcha = true;
                isShowRecaptcha(true);
                let re = webapiReCaptcha();
                re.reCaptchaId = this.expressRecaptchaId;
                re.settings = this.paymentConfig.recaptcha_settings;
                re.renderReCaptcha();
                if (this.isRecaptchaShared() || this.isRecaptchaInvisible()) {
                    $(this.recaptchaSelector).css({
                        'visibility': 'hidden',
                        'position': 'absolute',
                    });
                }
            }
        },

        isRecaptchaShared() {
            if (this.isCartPage()) return false;
            if (!window.checkoutConfig) return false;
            return window.checkoutConfig.payment.airwallex_payments.is_recaptcha_shared;
        },

        isRecaptchaInvisible() {
            return !!(this.paymentConfig && this.paymentConfig.recaptcha_type && this.paymentConfig.recaptcha_type !== 'recaptcha');
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
            return storage.get(url, undefined, 'application/json', {});
        },

        async getRegionId(country, region) {
            let url = urlBuilder.build('rest/V1/airwallex/region_id?country=' + country + '&region=' + region);
            return storage.get(url, undefined, 'application/json', {});
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

        isLoggedIn() {
            if (!this.isCheckoutPage()) {
                return !!this.expressData.customer_id;
            }
            return customer.isLoggedIn();
        },

        placeOrderUrl() {
            let serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order');
            if (this.isLoggedIn()) {
                serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
            }
            return serviceUrl;
        },

        awxAlert(msg) {
            return `
                <div class="awx-alert">
                    <div class="icon">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2204_2193)"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.1872 0.928627C11.6188 1.16392 11.9727 1.52225 12.205 1.95922L19.7001 16.0532C20.3559 17.2865 19.9003 18.8245 18.6823 19.4885C18.3174 19.6875 17.9093 19.7917 17.4948 19.7917H2.50467C1.12138 19.7917 0 18.6562 0 17.2556C0 16.8359 0.102869 16.4228 0.299381 16.0532L7.79447 1.95922C8.45029 0.725995 9.96927 0.264585 11.1872 0.928627ZM10 13.9583C9.30964 13.9583 8.75 14.518 8.75 15.2083C8.75 15.8987 9.30964 16.4583 10 16.4583C10.6904 16.4583 11.25 15.8987 11.25 15.2083C11.25 14.518 10.6904 13.9583 10 13.9583ZM10 6.45833C9.30964 6.45833 8.75 7.01798 8.75 7.70833V11.4583C8.75 12.1487 9.30964 12.7083 10 12.7083C10.6904 12.7083 11.25 12.1487 11.25 11.4583V7.70833C11.25 7.01798 10.6904 6.45833 10 6.45833Z"fill="#FF4F42"/></g><defs><clipPath id="clip0_2204_2193"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>
                    </div>
                    <div class="body"><span>${msg}</span></div>
                </div>
            `;
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
            payload.paymentMethod.additional_data.transaction_id = intentResponse.intent_id;
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

        async preverification(from, payload, self, quote) {
            if (!window.checkoutConfig.payment.airwallex_payments.is_pre_verification_enabled) return;
            let paymentMethodId = '';
            if (from === 'card') {
                if (!this.isLoggedIn() && !quote.guestEmail) {
                    throw new Error('Email address is required.');
                }
                if (!quote.billingAddress()) {
                    throw new Error('Billing address is required.');
                }
                let clientSecret, customerId;
                if (this.isLoggedIn()) {
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
                    console.log(err);
                    throw new Error($.mage.__('Invalid input. Please verify your payment details and try again.'));
                }
            } else if (from === 'vault') {
                paymentMethodId = self.paymentMethodId();
            }

            payload.paymentMethodId = paymentMethodId;
        },

        async sendBillingAddress(self, quote, from = "") {
            if (from !== 'vault') {
                await addressHandler.postBillingAddress({
                    'cartId': quote.getQuoteId(),
                    'address': quote.billingAddress()
                }, this.isLoggedIn(), quote.getQuoteId());
                return;
            }
            if (!window.airwallexSavedCards) {
                window.airwallexSavedCards = await this.getSavedCards();
            }
            for (let card of window.airwallexSavedCards) {
                if (card.id === $('#v-' + self.id).val()) {
                    self.paymentMethodId(card.payment_method_id);
                    if (!card.billing) {
                        continue;
                    }
                    let cardBilling = JSON.parse(card.billing);
                    let billing = {
                        firstname: cardBilling.first_name,
                        lastname: cardBilling.last_name,
                        telephone: cardBilling.phone_number || '000-00000000',
                        countryId: cardBilling.address.country_code,
                        regionId: 0,
                        region: cardBilling.address.state,
                        city: cardBilling.address.city, // taking "city1" from "city1-2"
                        street: cardBilling.address.street.split(', '),
                        postcode: cardBilling.address.postcode
                    };

                    billing.regionId = await this.getRegionId(cardBilling.address.country_code, cardBilling.address.state);
                    await addressHandler.postBillingAddress({
                        'cartId': quote.getQuoteId(),
                        'address': billing
                    }, this.isLoggedIn(), quote.getQuoteId());
                    break;
                }
            }
        },

        isRecaptchaEnabled() {
            return this.paymentConfig.is_recaptcha_enabled;
        },

        async setRecaptchaToken(payload, id) {
            if (this.isRecaptchaEnabled()) {
                if (id === this.expressRecaptchaId) {
                    payload.xReCaptchaValue = await new Promise((resolve, reject) => {
                        webapiRecaptchaRegistry.tokens = {};
                        webapiRecaptchaRegistry.addListener(id, (token) => {
                            resolve(token);
                        });
                        webapiRecaptchaRegistry.triggers[id]();
                    });
                } else {
                    let magentoRecaptchaRegistry = require('Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry');
                    if (magentoRecaptchaRegistry) {
                        if (magentoRecaptchaRegistry.tokens && magentoRecaptchaRegistry.tokens[id]) {
                            payload.xReCaptchaValue = magentoRecaptchaRegistry.tokens[id];
                        } else {
                            payload.xReCaptchaValue = await new Promise((resolve, reject) => {
                                magentoRecaptchaRegistry.addListener(id, (token) => {
                                    resolve(token);
                                });
                                magentoRecaptchaRegistry.triggers[id]();
                            });
                        }
                        magentoRecaptchaRegistry.tokens = {};
                    }
                }
                if (recaptchaRegistry.ids().length) {
                    let index = recaptchaRegistry.ids().indexOf(id);
                    if (index !== -1 && recaptchaRegistry.captchaList().length) {
                        let widgetId = recaptchaRegistry.captchaList()[index];
                        if (widgetId !== -1) {
                            grecaptcha.reset(widgetId);
                        }
                    }
                }
            }
        },

        async pay(self, from, quote) {
            $('body').trigger('processStart');

            const payload = {
                cartId: quote.getQuoteId(),
                from: from,
                paymentMethod: {
                    method: 'airwallex_payments_card',
                    additional_data: {},
                    extension_attributes: {
                        'agreement_ids': this.getAgreementIds()
                    },
                },
            };

            if (!this.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }

            let headers = {};
            _.each(placeOrderHooks.requestModifiers, function (modifier) {
                modifier(headers, payload);
            });

            payload.intent_id = null;

            if (from === 'card' && self.isSaveCardSelected() && self.getCustomerId()) {
                payload.from = 'card_with_saved';
            }

            await this.setRecaptchaToken(payload, this.getRecaptchaId());

            try {
                await this.sendBillingAddress(self, quote, from);
                await this.preverification(from, payload, self, quote);
                let intentResponse = await this.getIntent(payload, headers);
                try {
                    if (from === 'vault') {
                        const selectedConsentId = $("#v-" + $('input[name="payment[method]"]:checked').val()).val();
                        await Airwallex.confirmPaymentIntent({
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
                            let requestUrl = urlBuilder.build('rest/V1/airwallex/generate_client_secret');
                            let res = await storage.get(requestUrl, undefined, 'application/json', {});

                            await Airwallex.createPaymentConsent({
                                intent_id: intentResponse.intent_id,
                                customer_id: self.getCustomerId(),
                                client_secret: res.client_secret,
                                currency: quote.totals().quote_currency_code,
                                billing: self.getBillingInformation(),
                                element: self.cardNumberElement,
                                next_triggered_by: 'customer',
                            });
                        } else {
                            await Airwallex.confirmPaymentIntent({
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
                    if (error.code !== 'invalid_status_for_operation') {
                        throw error;
                    }
                }
                await this.placeOrder(payload, intentResponse, headers);
                this.clearDataAfterPay({}, customerData);
                window.location.replace(urlBuilder.build('airwallex/redirect?from=card&type=quote&id=' + quote.getQuoteId()));
            } catch (e) {
                if (e.message) {
                    self.validationError(e.message);
                } else if (e.responseJSON && e.responseJSON.message) {
                    self.validationError(e.responseJSON.message);
                } else {
                    self.validationError(e.responseText);
                }
                $('body').trigger('processStop');
                return;
            }

            _.each(placeOrderHooks.afterRequestListeners, function (listener) {
                listener();
            });
        }
    };
});
