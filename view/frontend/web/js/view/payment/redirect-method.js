define([
    'Airwallex_Payments/js/view/payment/abstract-method',
    'jquery',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (Component, $, url, globalMessageList, utils, additionalValidators, redirectOnSuccessAction, addressHandler, quote, $t) {
    'use strict';

    return Component.extend({
        type: 'redirect',
        redirectAfterPlaceOrder: false,
        defaults: {
            template: 'Airwallex_Payments/payment/redirect-method'
        },

        initObservable: function () {
            this.code = this.index;

            return this._super();
        },

        placeOrder: async function (data, event) {
            var self = this;

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
                    this.intentId(intentResponse.intent_id)
                } catch (e) { 
                    $('body').trigger('processStop'); 
                    self.isPlaceOrderActionAllowed(true);
                    utils.error(e); 
                    return;
                }

                this.getPlaceOrderDeferredObject()
                    .done(
                        function () {
                            self.afterPlaceOrder();

                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    ).always(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    );

                return true;
            }

            return false;
        },

        afterPlaceOrder: function () {
            $.ajax({
                url: url.build('rest/V1/airwallex/payments/redirect_url'),
                method: 'POST',
                contentType: 'application/json',
                data: {},
                beforeSend: function () {
                    $('body').trigger('processStart');
                },
                success: function (response) {
                    $.mage.redirect(response);
                },
                error: function (e) {
                    globalMessageList.addErrorMessage({
                        message: $t(e.responseJSON.message)
                    });
                    $('body').trigger('processStop');
                }
            });
        }
    });
});
