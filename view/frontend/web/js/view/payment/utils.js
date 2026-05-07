/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
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

        isSameBillingAddress(addr1, addr2) {
            if (!addr1 && !addr2) {
                return true;
            }
            if (!addr1 || !addr2) {
                return false;
            }

            const keys = ['countryId', 'regionId', 'region', 'city', 'postcode', 'street'];
            for (let i = 0; i < keys.length; i++) {
                const key = keys[i];
                if (key === 'street') {
                    const street1 = Array.isArray(addr1.street) ? addr1.street.join(',') : addr1.street;
                    const street2 = Array.isArray(addr2.street) ? addr2.street.join(',') : addr2.street;
                    if (street1 !== street2) {
                        return false;
                    }
                } else {
                    if (addr1[key] !== addr2[key]) {
                        return false;
                    }
                }
            }
            return true;
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
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2204_2193)"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.1872 0.928627C11.6188 1.16392 11.9727 1.52225 12.205 1.95922L19.7001 16.0532C20.3559 17.2865 19.9003 18.8245 18.6823 19.4885C18.3174 19.6875 17.9093 19.7917 17.4948 19.7917H2.50467C1.12138 19.7917 0 18.6562 0 17.2556C0 16.8359 0.102869 16.4228 0.299381 16.0532L7.79447 1.95922C8.45029 0.725995 9.96927 0.264585 11.1872 0.928627ZM10 13.9583C9.30964 13.9583 8.75 14.518 8.75 15.2083C8.75 15.8987 9.30964 16.4583 10 16.4583C10.6904 16.4583 11.25 15.8987 11.25 15.2083C11.25 14.518 10.6904 13.9583 10 13.9583ZM10 6.45833C9.30964 6.45833 8.75 7.01798 8.75 7.70833V11.4583C8.75 12.1487 9.30964 12.7083 10 12.7083C10.6904 12.7083 11.25 12.1487 11.25 11.4583V7.70833C11.25 7.01798 10.6904 6.45833 10 6.45833Z"fill="#FF4F42"></path></g><defs><clipPath id="clip0_2204_2193"><rect width="20" height="20" fill="white"></rect></clipPath></defs></svg>
                    </div>
                    <div class="body"><span>${msg}</span></div>
                </div>
            `;
        },

        getQueryParam(param) {
            const query = window.location.search.substring(1);
            const vars = query.split('&');
            for (let i = 0; i < vars.length; i++) {
                const pair = vars[i].split('=');
                if (decodeURIComponent(pair[0]) === param) {
                    return decodeURIComponent(pair[1]);
                }
            }
            return null;
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
            if (this.isCheckoutPage()) {
                return window.checkoutConfig.payment.airwallex_payments.is_recaptcha_enabled;
            }
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
        },

        showYouPay(switchers, $t) {
            switchers = switchers || {};
            $(".totals.charge").hide();
            let youPayElement = '.awx-you-pay';
            if (!$(youPayElement).length) {
                $(".table-totals tbody").append(
                    '<tr class="awx-you-pay"></tr>'
                );
            }

            let formattedTargetAmount = this.convertToAwxAmount(switchers.target_amount, switchers.target_currency);
            let formattedClientRate = switchers.client_rate;

            $(youPayElement).html(
                '<th class="mark" scope="row" style="padding-top: 33px;">' +
                    '<span style="font-size: 1.8rem; font-weight: 600;">' + $t('You Pay') + '</strong>' +
                '</th>' +
                '<td class="amount">' +
                    '<div class="switcher-tip">' +
                        '<div style="color: rgba(108, 116, 127, 1); margin-right: 5px;">1 ' + switchers.payment_currency + ' = ' + formattedClientRate + ' ' + switchers.target_currency + '</div>' +
                        '<svg width="12" height="24" viewBox="0 0 12 24" xmlns="http://www.w3.org/2000/svg">' +
                            '<line x1="6.5" y1="2.18557e-08" x2="6.5" y2="6" stroke="#E8EAED"></line>' +
                                '<path fill-rule="evenodd" clip-rule="evenodd" d="M10.8751 12.006C11.2227 11.8469 11.641 11.9755 11.836 12.3134C12.0431 12.6721 11.9202 13.1308 11.5615 13.3379L9.93769 14.2754C9.57897 14.4825 9.12028 14.3596 8.91317 14.0009L7.97567 12.3771C7.76857 12.0184 7.89147 11.5597 8.25019 11.3526C8.60891 11.1455 9.0676 11.2684 9.27471 11.6271L9.36849 11.7895C9.25886 10.0245 7.79267 8.62695 6.00007 8.62695C5.0122 8.62695 4.12347 9.05137 3.50626 9.72782L2.44482 8.66638C3.33417 7.71884 4.598 7.12695 6.00007 7.12695C8.69245 7.12695 10.8751 9.30957 10.8751 12.002C10.8751 12.0033 10.8751 12.0047 10.8751 12.006ZM1.12576 12.0887L1.12513 12.0891C0.766406 12.2962 0.307713 12.1733 0.100606 11.8146C-0.106501 11.4559 0.0164058 10.9972 0.375125 10.7901L1.99892 9.85256C2.35764 9.64545 2.81633 9.76836 3.02344 10.1271L3.96094 11.7509C4.16805 12.1096 4.04514 12.5683 3.68642 12.7754C3.3277 12.9825 2.86901 12.8596 2.6619 12.5009L2.66152 12.5002C2.90238 14.1279 4.30533 15.377 6 15.377C6.85293 15.377 7.63196 15.0606 8.22613 14.5387L9.28834 15.6009C8.42141 16.3935 7.26716 16.877 6 16.877C3.3366 16.877 1.17206 14.7411 1.12576 12.0887Z" fill="#B0B6BF"></path>' +
                            '<line x1="6.5" y1="18" x2="6.5" y2="24" stroke="#E8EAED"></line>' +
                        '</svg>' +
                    '</div>' +
                    '<div class="awx-amount">' + switchers.target_currency + ' ' + formattedTargetAmount + '</div>' +
                '</td>'
            );
            $(youPayElement).show();
        },

        hideYouPay() {
            const youPayElement = '.awx-you-pay';
            $(youPayElement).hide();
            $(".totals.charge").show();
        },

        customizedCurrencyOptions(currency) {
            // HUF, IDR, MGA, TWD should have 0 decimal places, different from the ISO 4217 standards
            let zeroDecimalCurrencies = ['IDR', 'HUF', 'MGA', 'TWD'];

            if (zeroDecimalCurrencies.indexOf(currency.toUpperCase()) !== -1) {
                return {
                    maximumFractionDigits: 0,
                    minimumFractionDigits: 0
                };
            }
            return {};
        },

        convertToAwxAmount(amount, currencyCode) {
            let customOptions = this.customizedCurrencyOptions(currencyCode);
            let formatterOptions = {
                style: 'currency',
                currency: currencyCode
            };

            // Merge customOptions into formatterOptions
            for (let key in customOptions) {
                if (customOptions.hasOwnProperty(key)) {
                    formatterOptions[key] = customOptions[key];
                }
            }

            let formatter = new Intl.NumberFormat('en-US', formatterOptions);
            let parts = formatter.formatToParts(Number(amount));
            let numberParts = [];

            for (let i = 0; i < parts.length; i++) {
                let part = parts[i];
                if (part.type === 'integer' || part.type === 'decimal' || part.type === 'fraction') {
                    numberParts.push(part.value);
                }
            }

            return numberParts.join('');
        }
    };
});
