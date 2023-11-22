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

define(['Airwallex_Payments/js/view/payment/abstract-method',
        'jquery',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_SalesRule/js/action/set-coupon-code',
        'Magento_SalesRule/js/action/cancel-coupon'
    ],
    function (Component, $, ko, quote, setCouponCodeAction, cancelCouponAction) {
    'use strict';

    return Component.extend({
        code: 'airwallex_payments_applepay',
        type: 'applePayButton',
        mountElement: 'applePayButton',
        loadingElement: false,
        defaults: {
            template: 'Airwallex_Payments/payment/embedded-method'
        },

        getElementConfiguration: function () {
            const billingAddress = quote.billingAddress(),
                grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2),
                currencyCode = quote.totals()['base_currency_code'];

            return {
                intent_id: this.intentConfiguration().id,
                client_secret: this.intentConfiguration().client_secret,
                amount: {
                    value: grandTotalAmount,
                    currency: currencyCode
                },
                totalPriceLabel: 'ApplePay (AirWallex)',
                buttonType: 'buy',
                buttonColor: 'white-with-line',
                origin: window.location.origin,
                countryCode: billingAddress.countryId,
            };
        },

        onPaymentMethodReady: function () {
            const buttonIframe = $('#' + this.mountElement + ' > iframe');
            if (!buttonIframe.attr('title')) {
                buttonIframe.parent().hide();
            }
        },
    });
});
