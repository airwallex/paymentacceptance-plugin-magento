define([
    'jquery',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'mage/url',
], function (
    $,
    utils,
    addressHandler,
    url,
) {
    'use strict';

    return {
        applepay: null,
        expressData: {},
        paymentConfig: {},
        from: '',
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

        create(that) {
            this.applepay = Airwallex.createElement('applePayButton', this.getRequestOptions());
            this.applepay.mount('awx-apple-pay-' + this.from);
            this.attachEvents(that);
            utils.loadRecaptcha(that.isShowRecaptcha);
        },

        confirmIntent(params) {
            return this.applepay.confirmIntent(params);
        },

        attachEvents(that) {
            this.applepay.on('click', () => {
                if (utils.isProductPage()) {
                    $('#btn-minicart-close').click();
                }
            });

            this.applepay.on('validateMerchant', async (event) => {
                try {
                    const merchantSession = await $.ajax(utils.postOptions({
                        validationUrl: event.detail.validationURL,
                        origin: window.location.host,
                    }, url.build('rest/V1/airwallex/payments/validate-merchant')));
                    this.applepay.completeValidation(JSON.parse(merchantSession));
                } catch (e) {
                    utils.error(e);
                }
            });

            this.applepay.on('shippingAddressChange', async (event) => {
                await utils.addToCart(that);

                this.intermediateShippingAddress = addressHandler.getIntermediateShippingAddress(event.detail.shippingAddress, 'apple');
                try {
                    await that.postAddress(this.intermediateShippingAddress);
                } catch (e) {
                    utils.error(e);
                }
                let options = this.getRequestOptions();
                if (utils.isRequireShippingOption()) {
                    options.shippingMethods = addressHandler.formatShippingMethodsToApple(this.methods, this.selectedMethod);
                }
                this.applepay.update(options);
            });

            this.applepay.on('shippingMethodChange', async (event) => {
                try {
                    await that.postAddress(this.intermediateShippingAddress, event.detail.shippingMethod.identifier);
                } catch (e) {
                    utils.error(e);
                }
                let options = this.getRequestOptions();
                options.shippingMethods = addressHandler.formatShippingMethodsToApple(this.methods, this.selectedMethod);
                this.applepay.update(options);
            });

            this.applepay.on('authorized', async (event) => {
                let shipping = event.detail.paymentData.shippingContact;
                let billing = event.detail.paymentData.billingContact;
                let phone, email;
                if (utils.isCheckoutPage()) {
                    let quote = require('Magento_Checkout/js/model/quote');
                    if (utils.isLoggedIn()) {
                        phone = quote.shippingAddress().telephone;
                        email = window.checkoutConfig.quoteData.customer_email;
                    } else {
                        phone = this.expressData.is_virtual ? shipping.phoneNumber : quote.shippingAddress().telephone;
                        email = $(utils.guestEmailSelector).val();
                    }
                } else {
                    phone = shipping.phoneNumber;
                    email = shipping.emailAddress;
                }
                that.setGuestEmail(email);

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
                addressHandler.setIntentConfirmBillingAddressFromApple(billing, email);
                that.placeOrder('applepay');
            });
        },

        getRequestOptions() {
            let paymentDataRequest = this.getOptions();

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

            const transactionInfo = {
                amount: {
                    value: utils.formatCurrency(this.expressData.grand_total),
                    currency: $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code,
                },
                lineItems: this.getDisplayItems(),
            };

            return Object.assign(paymentDataRequest, transactionInfo);
        },

        getOptions() {
            let options = {
                mode: 'payment',
                buttonColor: this.paymentConfig.express_style.theme,
                buttonType: this.paymentConfig.express_style.call_to_action,
                origin: window.location.origin,
                totalPriceLabel: this.paymentConfig.express_seller_name || '',
                countryCode: this.paymentConfig.country_code,
                requiredBillingContactFields: this.requiredBillingContactFields,
                requiredShippingContactFields: this.requiredShippingContactFields,
                autoCapture: this.paymentConfig.is_express_capture_enabled,
            };
            if (options.buttonType === 'checkout') {
                options.buttonType = 'check-out';
            }
            return options;
        },

        getDisplayItems() {
            let res = [];
            for (let key in this.expressData) {
                if (this.expressData[key] === '0.0000' || !this.expressData[key]) {
                    continue;
                }
                if (key === 'shipping_amount') {
                    res.push({
                        'label': 'Shipping',
                        'amount': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'tax_amount') {
                    res.push({
                        'label': 'Tax',
                        'amount': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal') {
                    res.push({
                        'label': 'Subtotal',
                        'amount': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal_with_discount') {
                    if (this.expressData[key] !== this.expressData['subtotal']) {
                        res.push({
                            'label': 'Discount',
                            'amount': '-' + utils.getDiscount(this.expressData['subtotal'], this.expressData['subtotal_with_discount']).toString()
                        });
                    }
                }
            }
            return res;
        },
    };
});
