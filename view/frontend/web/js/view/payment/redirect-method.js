define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
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
    ko,
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
        validationError: ko.observable(),
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
            this.validationError('');

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
                    const iframeSelector = "._active .qrcode-payment .iframe";
                    const qrcodeSelector = "._active .qrcode-payment .qrcode";
                    $(iframeSelector).html('').hide();
                    $(qrcodeSelector).html('').hide();
                    $("._active .qrcode-payment").css('display', 'flex');
                    let nextAction = JSON.parse(intentResponse.next_action);
                    // url qrcode_url qrcode
                    if (['airwallex_payments_pay_now'].indexOf(this.code) === -1) {
                        $(qrcodeSelector).show();
                        new QRCode(document.querySelector(qrcodeSelector), nextAction.qrcode);
                        $('body').trigger('processStop');
                    } else {
                        $(iframeSelector).show();
                        $(iframeSelector).html(`<iframe src="${nextAction.url}"></iframe>`);
                        const iframeElement = $(iframeSelector).find('iframe');
                        let setHeight = function() {
                            let height = 1200;
                            if (window.innerWidth > 768 && window.innerWidth <= 1000) {
                                height = window.innerWidth * 1.3;
                            } else if (window.innerWidth > 550 && window.innerWidth < 640) {
                                height = window.innerWidth * 2
                            } else if (window.innerWidth <= 550) {
                                height = 1100;
                            }
                            $(iframeElement).height(height);
                        };
                        setHeight();
                        iframeElement.on('load', function() {
                            window.addEventListener('resize', setHeight)
                            $('body').trigger('processStop');
                        });
                    }
                    if (this.timer) clearInterval(this.timer);
                    this.timer = setInterval(async () => {
                        let res = await this.getIntent(intentResponse.intent_id);
                        let response = JSON.parse(res);
                        if (response.paid && response.is_order_status_changed) {
                            utils.clearDataAfterPay(response, customerData);
                            redirectOnSuccessAction.execute();
                        }
                    }, 2500);
                } catch (e) {
                    console.error(e);
                    if (e.responseJSON && e.responseJSON.message) {
                        this.validationError($t(e.responseJSON.message));
                    } else {
                        this.validationError($t('Something went wrong while processing your request. Please try again.'));
                    }
                    $('body').trigger('processStop');
                    return;
                } finally {
                    self.isPlaceOrderActionAllowed(true);
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
