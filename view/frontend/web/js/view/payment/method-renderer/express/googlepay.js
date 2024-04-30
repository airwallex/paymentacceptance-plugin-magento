define([
    'jquery',
    'Airwallex_Payments/js/view/payment/method-renderer/express/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
], function (
    $,
    utils,
    addressHandler,
) {
    'use strict';

    return {
        googlepay: null,
        expressData: {},
        paymentConfig: {},
        from: '',
        methods: [],
        selectedMethod: {},

        create(that) {
            this.googlepay = Airwallex.createElement('googlePayButton', this.getRequestOptions());
            this.googlepay.mount('awx-google-pay-' + this.from);
            this.attachEvents(that);
            utils.loadRecaptcha(that.isShowRecaptcha);
        },

        confirmIntent(params) {
            return this.googlepay.confirmIntent(params);
        },

        attachEvents(that) {
            let updateQuoteByShipment = async (event) => {
                await utils.addToCart(that);

                let addr = addressHandler.getIntermediateShippingAddress(event.detail.intermediatePaymentData.shippingAddress);

                try {
                    let methodId = "";
                    if (event.detail.intermediatePaymentData.shippingOptionData) {
                        methodId = event.detail.intermediatePaymentData.shippingOptionData.id;
                    }
                    await that.postAddress(addr, methodId);
                    let options = this.getRequestOptions();
                    if (utils.isRequireShippingOption()) {
                        options.shippingOptionParameters = addressHandler.formatShippingMethodsToGoogle(this.methods, this.selectedMethod);
                    }
                    this.googlepay.update(options);
                } catch (e) {
                    utils.error(e);
                }
            };

            this.googlepay.on('shippingAddressChange', updateQuoteByShipment);

            this.googlepay.on('shippingMethodChange', updateQuoteByShipment);

            this.googlepay.on('authorized', async (event) => {
                that.setGuestEmail(event.detail.paymentData.email);
                if (utils.isRequireShippingAddress()) {
                    // this time google provide full shipping address, we should post to magento
                    let information = addressHandler.constructAddressInformationFromGoogle(
                        event.detail.paymentData
                    );
                    await addressHandler.postShippingInformation(information, utils.isLoggedIn(), utils.getCartId());
                } else {
                    await addressHandler.postBillingAddress({
                        'cartId': utils.getCartId(),
                        'address': addressHandler.getBillingAddressFromGoogle(event.detail.paymentData.paymentMethodData.info.billingAddress)
                    }, utils.isLoggedIn(), utils.getCartId());
                }
                addressHandler.setIntentConfirmBillingAddressFromGoogle(event.detail.paymentData);
                that.placeOrder();
            });
        },

        getRequestOptions() {
            let paymentDataRequest = this.getOptions();
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
            };
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
                        'type': 'LINE_ITEM',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'tax_amount') {
                    res.push({
                        'label': 'Tax',
                        'type': 'TAX',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal') {
                    res.push({
                        'label': 'Subtotal',
                        'type': 'SUBTOTAL',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal_with_discount') {
                    if (this.expressData[key] !== this.expressData['subtotal']) {
                        res.push({
                            'label': 'Discount',
                            'type': 'LINE_ITEM',
                            'price': '-' + utils.getDiscount(this.expressData['subtotal'], this.expressData['subtotal_with_discount']).toString()
                        });
                    }
                }
            }
            return res;
        },
    };
});
