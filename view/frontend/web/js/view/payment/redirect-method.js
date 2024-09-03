define([
    'Airwallex_Payments/js/view/payment/abstract-method',
    'jquery',
    'mage/url',
    'mage/storage',
    'Magento_Ui/js/model/messageList',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (
    Component,
    $,
    url,
    storage,
    globalMessageList,
    utils,
    additionalValidators,
    redirectOnSuccessAction,
    addressHandler,
    quote,
    customerData,
    $t
) {
    'use strict';

    return Component.extend({
        type: 'redirect',
        redirectAfterPlaceOrder: false,
        defaults: {
            timer: null,
            template: 'Airwallex_Payments/payment/redirect-method'
        },

        initObservable: function () {
            this.code = this.index;

            return this._super();
        },

        placeOrder: async function (data, event) {
            let self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                this.isPlaceOrderActionAllowed(false);

                $('body').trigger('processStart');

                try {
                    const payload = {
                        cartId: quote.getQuoteId(),
                        paymentMethod: {
                            method: this.code,
                            additional_data: {},
                            extension_attributes: {
                                'agreement_ids': utils.getAgreementIds()
                            }
                        },
                    };

                    if (window.checkoutConfig.payment.airwallex_payments.recaptcha_enabled) {
                        let recaptchaRegistry = require('Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry');
                        if (recaptchaRegistry) {
                            payload.xReCaptchaValue = await new Promise((resolve, reject) => {
                                recaptchaRegistry.tokens = {};
                                recaptchaRegistry.addListener(utils.getRecaptchaId(), (token) => {
                                    resolve(token);
                                });
                                recaptchaRegistry.triggers[utils.getRecaptchaId()]();
                            });
                            recaptchaRegistry.tokens = {};
                        }
                    }

                    if (!utils.isLoggedIn()) {
                        payload.email = quote.guestEmail;
                    }

                    await addressHandler.postBillingAddress({
                        'cartId': quote.getQuoteId(),
                        'address': quote.billingAddress()
                    }, utils.isLoggedIn(), quote.getQuoteId());

                    let intentResponse = await utils.getIntent(payload, {});
                    this.intentId(intentResponse.intent_id);
                    $("._active .qrcode-payment .qrcode").html('');
                    $("._active .qrcode-payment").css('display', 'flex');
                    let nextAction = JSON.parse(intentResponse.next_action);
                    console.log(nextAction, this.code)
                    
                    // url qrcode_url qrcode
                    if (['airwallex_payments_pay_now'].indexOf(this.code) === -1) {
                        new QRCode(document.querySelector(".airwallex-payments._active .qrcode"), nextAction.qrcode);
                    } else {

                    }
                    if (this.timer) clearInterval(this.timer);
                    this.timer = setInterval(async () => {
                        let res = await this.getIntent(intentResponse.intent_id);
                        let response = JSON.parse(res);
                        if (response.paid) {
                            utils.clearDataAfterPay(response, customerData);
                            redirectOnSuccessAction.execute();
                        }
                    }, 2500);
                } catch (e) {
                    utils.error(e);
                    return;
                } finally {
                    self.isPlaceOrderActionAllowed(true);
                    $('body').trigger('processStop');
                }
                return true;
            }
            return false;
        },

        async getIntent(intentId) {
            let requestUrl = url.build('rest/V1/airwallex/payments/intent?intent_id=' + intentId);
            return storage.get(requestUrl, undefined, 'application/json', {});
        },
    });
});
