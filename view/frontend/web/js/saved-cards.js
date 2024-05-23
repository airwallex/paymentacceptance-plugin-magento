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
    'Magento_Customer/js/customer-data',
    'mage/url'
],function ($, Component, ko, customer, customerData, url) {
    'use strict';

    return Component.extend({
        defaults: {
            template:          'Airwallex_Payments/saved-cards',
            savedPaymentsUrl:  url.build('rest/V1/airwallex/saved_cards/'),
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

        delete(card) {
            let removeUrl = this.savedPaymentsUrl + card.id;
            $('body').trigger('processStart');
        
            $.ajax({
                url: removeUrl,
                method: 'DELETE',
                success: (function() {
                    $('body').trigger('processStop');
                    let res = this.paymentMethods().filter(item => item.id !== card.id);
                    this.paymentMethods((res && res.length) ? res : false);
                    setTimeout(function() {
                        customerData.set('messages', {
                             messages: [{
                                 type: 'success',
                                 text: 'Your saved Visa card ending in ' + card.card_last_four + ' was successfully deleted.'
                                }]
                         });
                   }, 1000);
                }).bind(this),
                error: function(xhr, status, error) {
                    $('body').trigger('processStop');
                    $('.modal-content div').html('');
                }
            });
        },

        deletePaymentMethod(card) {
            let that = this;
            $.mage.confirm({
                title: $.mage.__('Delete saved card'),
                buttons: [{
                    text: $.mage.__('Delete'),
                    class: 'action-primary action-accept',
                    click: function() {
                        this.closeModal(event, true);
                        that.delete(card);
                    }
                }, {
                    text: $.mage.__('Cancel'),
                    click: function(event) {
                        this.closeModal(event, true);
                        $('.modal-content div').html('');
                    }
                }]
            });
            let msg = 'You are deleting your saved ' + card.card_brand + ' card ending in ' + card.card_last_four + '.';
            $('.modal-content div').html($.mage.__(msg));
        }
    });
});
