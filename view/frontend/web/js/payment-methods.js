define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'mage/url'
],function ($, Component, ko, customer, url) {
    'use strict';

    return Component.extend({
        defaults: {
            template:          'Airwallex_Payments/payment-methods',
            savedPaymentsUrl:  url.build('rest/V1/airwallex/saved_payments/'),
            paymentMethods:    null
        },

        initialize: function () {
            this._super();
        },

        initObservable: function () {
            this._super()
                .observe('paymentMethods');

            this.getPaymentMethods();

            return this;
        },

        getPaymentMethods: function () {
            $.ajax({
                url: this.savedPaymentsUrl,
                method: 'GET',
                success: (function(response) {
                    if (response.length > 0) {
                        this.paymentMethods(response);
                    } else {
                        this.paymentMethods(false);
                    }
                }).bind(this),
                error: function(xhr, status, error) {
                    console.error(status, error);
                }
            });
        },

        deletePaymentMethod: function (method) {
            var removeUrl = this.savedPaymentsUrl + method.id;

            $.ajax({
                url: removeUrl,
                method: 'DELETE',
                success: (function() {
                    location.reload();
                }).bind(this),
                error: function(xhr, status, error) {
                    console.error(status, error);
                }
            });
        }
    });
});
