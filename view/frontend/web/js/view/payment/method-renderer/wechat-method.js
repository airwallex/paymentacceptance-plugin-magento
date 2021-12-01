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
        'Magento_SalesRule/js/action/set-coupon-code',
        'Magento_SalesRule/js/action/cancel-coupon'],
    function (Component, $, setCouponCodeAction, cancelCouponAction) {
    'use strict';

    return Component.extend({
        code: 'airwallex_payments_wechat',
        type: 'wechat',
        mountElement: 'airwallex-payments-wechat-form',
        defaults: {
            template: 'Airwallex_Payments/payment/wechat-method'
        },

        loadPayment: function() {
            if (this.isChecked() === this.code) {
                this._super();
                setCouponCodeAction.registerSuccessCallback(this.reloadElement.bind(this));
                cancelCouponAction.registerSuccessCallback(this.reloadElement.bind(this));
            }
        },

        reloadElement: function() {
            Airwallex.destroyElement('wechat');
            this.refreshIntent();
            this.initPayment();
        }
    });
});
