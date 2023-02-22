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
    'Airwallex_Payments/js/view/payment/abstract-method',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/payment/additional-validators',
    ], function ($, ko, Component, quote, additionalValidators) {
        'use strict';

        return Component.extend({
            code: 'airwallex_payments_card',
            type: 'card',
            mountElement: 'airwallex-payments-card-form',
            cardElement: undefined,
            validationError: ko.observable(),
            defaults: {
                template: 'Airwallex_Payments/payment/card-method'
            },

            getBillingInformation: function () {
                const billingAddress = quote.billingAddress();

                return {
                    address: {
                        city: billingAddress.city,
                        country_code: billingAddress.countryId,
                        postcode: billingAddress.postcode,
                        state: billingAddress.region,
                        street: billingAddress.street[0]
                    },
                    first_name: billingAddress.firstname,
                    last_name: billingAddress.lastname,
                    email: quote.guestEmail
                }
            },

            initiateOrderPlacement: function () {
                if (!additionalValidators.validate()) {
                    return;
                }
                $('body').trigger('processStart');
                this.createIntent();
                const params = this.intentConfiguration();
                params.payment_method = {};
                params.payment_method.billing = this.getBillingInformation();
                params.element = this.cardElement;
                this.validationError(undefined);

                Airwallex
                    .confirmPaymentIntent(params)
                    .then(function (response) {
                        this.paymentSuccess(response);
                        $('body').trigger('processStop');
                    }.bind(this))
                    .catch(function (response) {
                        this.validationError(response.message);
                        $('body').trigger('processStop');
                    }.bind(this));
            },

            initPayment: function () {
                this.cardElement = Airwallex.createElement(this.type, {
                    autoCapture: window.checkoutConfig.payment.airwallex_payments.cc_auto_capture
                });
                this.cardElement.mount(this.mountElement);

                window.addEventListener('onReady', function () {
                    $('body').trigger('processStop');
                }, {once: true});
            }
        });
    });
