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
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'airwallex_payments_card',
        component: 'Airwallex_Payments/js/view/payment/method-renderer/card-method'
    });

    rendererList.push({
        type: 'airwallex_payments_wechat',
        component: 'Airwallex_Payments/js/view/payment/method-renderer/wechat-method'
    });

    rendererList.push({
        type: 'airwallex_payments_alipaycn',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_dana',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_alipayhk',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_gcash',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_kakaopay',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_tng',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_googlepay',
        component: 'Airwallex_Payments/js/view/payment/method-renderer/googlepay-method'
    });

    rendererList.push({
        type: 'airwallex_payments_applepay',
        component: 'Airwallex_Payments/js/view/payment/method-renderer/applepay-method'
    });

    return Component.extend({});
});
