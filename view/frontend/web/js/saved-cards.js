/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit,
 Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
 please view the LICENSE
 * file that was distributed with this source code.
 */
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
            template:          'Airwallex_Payments/saved-cards',
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
            $('body').trigger('processStart');
        
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
