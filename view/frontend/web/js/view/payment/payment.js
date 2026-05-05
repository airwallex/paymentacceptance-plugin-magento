/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
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
        type: 'airwallex_payments_wechatpay',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
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
        type: 'airwallex_payments_pay_now',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_klarna',
        component: 'Airwallex_Payments/js/view/payment/redirect-method/klarna'
    });

    rendererList.push({
        type: 'airwallex_payments_afterpay',
        component: 'Airwallex_Payments/js/view/payment/redirect-method/afterpay'
    });

    rendererList.push({
        type: 'airwallex_payments_bank_transfer',
        component: 'Airwallex_Payments/js/view/payment/redirect-method/bank-transfer'
    });

    rendererList.push({
        type: 'airwallex_payments_ideal',
        component: 'Airwallex_Payments/js/view/payment/redirect-method'
    });

    rendererList.push({
        type: 'airwallex_payments_apm',
        component: 'Airwallex_Payments/js/view/payment/apm-method'
    });

    return Component.extend({});
});
