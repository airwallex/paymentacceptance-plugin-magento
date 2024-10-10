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

        async sendVaultBillingAddress(self, quote, from = "") {
            if (from !== 'vault') return;
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

            if (from === 'card' && self.isSaveCardSelected() && self.getCustomerId()) {
                payload.from = 'card_with_saved';
            }

            await this.setRecaptchaToken(payload, this.getRecaptchaId());

            try {
                await this.sendVaultBillingAddress(self, quote, from);
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
                this.redirectToSuccess();
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
