/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/* global Airwallex */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'Airwallex_Payments/js/util/pow',
    'mage/translate'
], function ($, ko, Component, url, globalMessageList, powUtil) {
    'use strict';

    return Component.extend({
        code: 'airwallex_payments_card',
        type: undefined,
        mountElement: undefined,
        fonts: [{
            src: 'https://checkout.airwallex.com/fonts/CircularXXWeb/CircularXXWeb-Regular.woff2',
            family: 'AxLLCircular',
            weight: 400,
        }],
        defaults: {
            template: 'Airwallex_Payments/payment/card-method'
        },
        responseData: {},
        readyLoaded: {},
        intentId: ko.observable(),
        amount: ko.observable(0),
        intentStatus: ko.observable(0),

        initObservable: function () {
            this._super();

            this.isChecked.subscribe(function (method) {
                if (method !== this.code) {
                    return;
                }

                if (!this.readyLoaded[method]) {
                    this.loadPayment();
                }
            }, this);

            return this;
        },

        afterRender: function () {
            if (this.isChecked() === this.code) {
                this.loadPayment();
            }
        },

        /**
         * Get payment method data
         */
        getData: function () {
            let data = this._super();

            data['additional_data'] = {
                'intent_id': this.intentId(),
                'amount': this.amount(),
                'intent_status': this.intentStatus(),
            };

            return data;
        },

        loadPayment: function () {
            $('body').trigger('processStart');
            if (this.type !== 'redirect') {
                Airwallex.init({
                    env: window.checkoutConfig.payment.airwallex_payments.mode,
                    origin: window.location.origin,
                    fonts: this.fonts
                });

                this.initPayment().then(() => {
                    this.readyLoaded[this.code] = true;
                });
            } else {
                $('body').trigger('processStop');
            }

        },

        paymentSuccess: function (intent) {
            this.intentStatus(intent.status);
            this.amount(intent.amount);
            this.placeOrder();
        },

        intentConfiguration() {
            return {
                id: this.responseData.id,
                client_secret: this.responseData.clientSecret
            }
        },

        getElementConfiguration: function () {
            return {
                autoCapture: false,
                intent: this.intentConfiguration()
            };
        },

        initPayment: async function () {
            if(!this.intentConfiguration().id){
                await this.createIntent();
            }
            const airwallexElement = Airwallex.createElement(this.type, this.getElementConfiguration());
            airwallexElement.mount(this.mountElement);

            $('body').trigger('processStop');
            window.addEventListener('onReady', function () {
                $('body').trigger('processStop');
            });

            window.addEventListener('onSuccess', function (event) {
                this.paymentSuccess(event.detail.intent);
            }.bind(this));

            window.addEventListener('onError', function (event) {
                console.log(event.detail);
            });
        },

        createIntent: async function () {
            const method = this.isChecked();
            const payload = {
                'method': method,
                'powSolution': await powUtil.solvePOW()
            }
            $.ajax({
                url: url.build('rest/V1/airwallex/payments/create_intent'),
                method: 'POST',
                contentType: 'application/json',
                async:false,
                data: JSON.stringify(payload),
                success: function (result) {
                    this.responseData = JSON.parse(result);
                    this.intentId(this.responseData.id);
                }.bind(this),
                error: function () {
                    globalMessageList.addErrorMessage({
                        message: $.mage.__('An error occurred on the server. Please try to place the order again.'),
                    });

                    $('body').trigger('processStop');
                }
            });
        },

        refreshIntent: async function () {
            const payload = {
                'intentId': this.intentConfiguration().id,
                'method': this.isChecked(),
                'powSolution': await powUtil.solvePOW()
            }
            $.ajax({
                url: url.build('rest/V1/airwallex/payments/refresh_intent'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                async:false,
                success: function (result) {
                    this.responseData = JSON.parse(result);
                    this.intentId(this.responseData.id);
                }.bind(this),
                error: function () {
                    globalMessageList.addErrorMessage({
                        message: $.mage.__('An error occurred on the server. Please try to place the order again.'),
                    });

                    $('body').trigger('processStop');
                }
            });
        }
    });
});
