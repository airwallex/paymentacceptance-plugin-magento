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

define([
    'Airwallex_Payments/js/view/payment/abstract-method',
    'jquery',
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function (Component, $, url, globalMessageList, $t) {
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
