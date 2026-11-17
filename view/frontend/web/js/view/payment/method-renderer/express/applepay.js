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
/** Options built by `getOptions` then extended by `getRequestOptions`. */
/** `this` receiver for the applepay module's methods (the returned object). */
define([
    'jquery',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'mage/url',
], function (
    $              ,
    utils             ,
    addressHandler                      ,
    url                                 ,
) {
    'use strict';
    return {
        elements: {},
        expressData: {},
        paymentConfig: {},
        methods: [],
        selectedMethod: {},
        intermediateShippingAddress: {},
        requiredShippingContactFields: [
            'email',
            'name',
            'phone',
            'postalAddress',
        ],
        requiredBillingContactFields: [
            'postalAddress',
        ],
        create(                    that                     ) {
            let element = Airwallex.createElement('applePayButton', this.getRequestOptions(true)                                            );
            this.elements[that.from] = element;
            let el = element.mount('awx-apple-pay-' + that.from);
            utils.attachHeightGuard(element, 'applePayButton');
            el.addEventListener('onReady', (event) => {
                utils.initCheckoutPageExpressCheckoutClick();
                if (that.deviceSupportApplePay()) {
                    $(".express-title").show();
                    utils.showAgreements();
                } else {
                    if (!that.isGooglePayActive()) $(".airwallex-recaptcha").hide();
                }
            });
            this.attachEvents(that, element);
            utils.loadRecaptcha(that.isShowRecaptcha);
        },
        confirmIntent(                    from        , params                         ) {
            const paymentData = this.authorizedPaymentData && this.authorizedPaymentData[from];
            // Airwallex keeps a shipping field whenever it is truthy, so a full
            // shipping contact (product, cart, minicart) replaces the billing address.
            if (paymentData && paymentData.billingContact && !utils.isCheckoutPage()) {
                const billing = paymentData.billingContact;
                const shipping = paymentData.shippingContact || {};
                params = {
                    ...params,
                    billingContact: billing,
                    shippingContact: {
                        phoneNumber: billing.phoneNumber || shipping.phoneNumber,
                        emailAddress: billing.emailAddress || shipping.emailAddress,
                        givenName: billing.givenName || shipping.givenName,
                        familyName: billing.familyName || shipping.familyName,
                    },
                };
            }
            return this.elements[from].confirmIntent(params);
        },
        destroy(                    from        ) {
            if (this.elements[from]) {
                utils.detachHeightGuard(this.elements[from]);
                this.elements[from].destroy();
                delete this.elements[from];
            }
        },
        attachEvents(                    that                     , element                  ) {
            // Product-page wallets must prepare a fresh cart in every payment session.
            let cartReady = that.from !== 'product_page';
            let cartPrepared = cartReady;
            const reportCartError = (shippingMethodChange = false) => {
                const errors                                                = [{
                    code: 'unknown',
                    message: $.mage.__('Unable to prepare your cart. Please close the payment window and try again.'),
                }];
                // Always supply the current amount: SDK fallback uses the initial product-page total (zero).
                const options = this.getRequestOptions();
                if (shippingMethodChange) {
                    // Apple Pay shipping-method updates have no errors field; show the failure in the summary.
                    options.lineItems = [...this.getDisplayItems(), {
                        label: $.mage.__('Unable to update shipping. Please select another shipping method or close the payment window and try again.'),
                        amount: '0.00',
                        type: 'pending',
                    }];
                }
                element.update({ ...options, errors });
            };
            element.on('click', () => {
                cartReady = that.from !== 'product_page';
                cartPrepared = cartReady;
                if (utils.isProductPage()) {
                    $('#btn-minicart-close').click();
                }
            });
            element.on('validateMerchant', async (event     ) => {
                try {
                    const merchantSession = await $.ajax(utils.postOptions({
                        validationUrl: event.detail.validationURL,
                        origin: window.location.host,
                    }, url.build('rest/V1/airwallex/payments/validate-merchant')));
                    element.completeValidation(JSON.parse(merchantSession));
                } catch (e) {
                    utils.error(e);
                }
            });
            element.on('shippingAddressChange', async (event     ) => {
                cartReady = false;
                cartPrepared = false;
                try {
                    await utils.addToCart(that);
                    this.intermediateShippingAddress = addressHandler.getIntermediateShippingAddress(event.detail.shippingAddress, 'apple');
                    cartPrepared = true;
                    await that.postAddress(this.intermediateShippingAddress);
                } catch (e) {
                    reportCartError();
                    utils.error(e);
                    return;
                }
                let options = this.getRequestOptions();
                if (utils.isRequireShippingOption()) {
                    options.shippingMethods = addressHandler.formatShippingMethodsToApple(this.methods, this.selectedMethod);
                }
                element.update({ ...options, errors: [] });
                cartReady = true;
            });
            element.on('shippingMethodChange', async (event     ) => {
                if (!cartPrepared) {
                    reportCartError(true);
                    return;
                }
                cartReady = false;
                try {
                    await that.postAddress(this.intermediateShippingAddress, event.detail.shippingMethod.identifier);
                } catch (e) {
                    reportCartError(true);
                    utils.error(e);
                    return;
                }
                let options = this.getRequestOptions();
                options.shippingMethods = addressHandler.formatShippingMethodsToApple(this.methods, this.selectedMethod);
                element.update({ ...options, errors: [] });
                cartReady = true;
            });
            element.on('authorized', async (event     ) => {
                if (!cartReady) {
                    reportCartError();
                    return;
                }
                let shipping = event.detail.paymentData.shippingContact;
                let billing = event.detail.paymentData.billingContact;
                let phone, email;
                if (utils.isCheckoutPage()) {
                    let quote = require('Magento_Checkout/js/model/quote');
                    if (utils.isLoggedIn()) {
                        phone = quote.shippingAddress().telephone;
                        email = window.checkoutConfig .quoteData .customer_email;
                    } else {
                        phone = this.expressData.is_virtual ? shipping.phoneNumber : quote.shippingAddress().telephone;
                        email = $(utils.guestEmailSelector).val();
                    }
                } else {
                    phone = shipping.phoneNumber;
                    email = shipping.emailAddress;
                }
                that.setGuestEmail(email);
                try {
                    if (utils.isRequireShippingAddress()) {
                        // this time Apple provide full shipping address, we should post to magento
                        let information = addressHandler.constructAddressInformationFromApple(
                            event.detail.paymentData
                        );
                        await addressHandler.postShippingInformation(information, utils.isLoggedIn(), utils.getCartId());
                    } else {
                        await addressHandler.postBillingAddress({
                            'cartId': utils.getCartId(),
                            'address': addressHandler.getBillingAddressFromApple(billing, phone)
                        }, utils.isLoggedIn(), utils.getCartId());
                    }
                    addressHandler.setIntentConfirmBillingAddressFromApple(billing, email, phone);
                    this.authorizedPaymentData = this.authorizedPaymentData || {};
                    this.authorizedPaymentData[that.from] = event.detail.paymentData;
                    that.placeOrder('applepay');
                } catch (e) {
                    utils.error(e);
                }
            });
        },
        getRequestOptions(                    initial          = false)                         {
            let paymentDataRequest = this.getOptions()                          ;
            if (utils.isCheckoutPage()) {
                paymentDataRequest.requiredShippingContactFields = [];
                if (this.expressData.is_virtual && !utils.isLoggedIn()) {
                    paymentDataRequest.requiredShippingContactFields = ['phone'];
                }
            } else if (!utils.isProductPage()) {
                if (this.expressData.is_virtual) {
                    paymentDataRequest.requiredShippingContactFields = ['phone'];
                } else {
                    paymentDataRequest.requiredShippingContactFields = ['phone', 'postalAddress'];
                }
                if (!utils.isLoggedIn()) {
                    paymentDataRequest.requiredShippingContactFields.push('email')
                }
            } else {
                paymentDataRequest.requiredShippingContactFields = ['phone', 'postalAddress'];
                if (!utils.isLoggedIn()) {
                    paymentDataRequest.requiredShippingContactFields.push('email')
                }
            }
            const showZero = initial && utils.isProductPage();
            const currency = $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code;
            const transactionInfo = {
                amount: {
                    value: utils.formatCurrency(showZero ? 0 : this.expressData.grand_total , currency),
                    currency: currency,
                },
                lineItems: showZero ? [] : this.getDisplayItems(),
            };
            return Object.assign(paymentDataRequest, transactionInfo);
        },
        getSupportedNetworks(supportBrands          )           {
            let brands = supportBrands.map(function (brand) {
                    if (brand === 'unionpay') {
                        return 'chinaUnionPay';
                    }
                    if (brand === 'mastercard') {
                        return 'masterCard';
                    }
                    return brand;
                }).filter(function (brand) {
                    return brand !== 'diners';
                });
            if (brands.indexOf('masterCard') !== -1 && brands.indexOf('maestro') === -1) {
                brands.push('maestro');
            }
            return brands;
        },
        getOptions(                  )                         {
            let options                         = {
                mode: 'payment',
                buttonColor: this.paymentConfig.express_style .theme,
                buttonType: this.paymentConfig.express_style .call_to_action,
                origin: window.location.origin,
                totalPriceLabel: this.paymentConfig.express_seller_name || '',
                countryCode: this.paymentConfig.country_code ,
                requiredBillingContactFields: this.requiredBillingContactFields,
                requiredShippingContactFields: this.requiredShippingContactFields,
                autoCapture: this.paymentConfig.is_express_auto_capture ,
                supportedNetworks: this.getSupportedNetworks(this.paymentConfig.allowed_card_networks .applepay),
            };
            if (options.buttonType === 'checkout') {
                options.buttonType = 'check-out';
            }
            return options;
        },
        getDisplayItems(                  )                                 {
            let res                                 = [];
            const currency = $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code;
            for (let key in this.expressData) {
                if (this.expressData[key] === '0.0000' || !this.expressData[key]) {
                    continue;
                }
                if (key === 'shipping_amount') {
                    res.push({
                        'label': 'Shipping',
                        'amount': utils.formatCurrency(this.expressData[key], currency)
                    });
                } else if (key === 'tax_amount') {
                    res.push({
                        'label': 'Tax',
                        'amount': utils.formatCurrency(this.expressData[key], currency)
                    });
                } else if (key === 'subtotal') {
                    res.push({
                        'label': 'Subtotal',
                        'amount': utils.formatCurrency(this.expressData[key], currency)
                    });
                } else if (key === 'subtotal_with_discount') {
                    if (this.expressData[key] !== this.expressData['subtotal']) {
                        res.push({
                            'label': 'Discount',
                            'amount': '-' + utils.getDiscount(this.expressData['subtotal'] , this.expressData['subtotal_with_discount'] , currency).toString()
                        });
                    }
                }
            }
            return res;
        },
    };
});
