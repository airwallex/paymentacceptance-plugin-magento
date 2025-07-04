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

    return Component.extend({});
});
