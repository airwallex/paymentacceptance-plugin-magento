<?php

namespace Airwallex\Payments\Model\Methods;

class RedirectMethod extends AbstractMethod
{
    public const DANA_CODE = 'airwallex_payments_dana';
    public const ALIPAYCN_CODE = 'airwallex_payments_alipaycn';
    public const ALIPAYHK_CODE = 'airwallex_payments_alipayhk';
    public const GCASH_CODE = 'airwallex_payments_gcash';
    public const KAKAO_CODE = 'airwallex_payments_kakaopay';
    public const TOUCH_N_GO_CODE = 'airwallex_payments_tng';
    public const WECHAT_CODE = 'airwallex_payments_wechatpay';
    public const PAY_NOW_CODE = 'airwallex_payments_pay_now';
    public const KLARNA_CODE = 'airwallex_payments_klarna';
    public const AFTERPAY_CODE = 'airwallex_payments_afterpay';
    public const BANK_TRANSFER_CODE = 'airwallex_payments_bank_transfer';

    public const CURRENCY_SWITCHER_METHODS = [
        KlarnaMethod::CODE,
        AfterpayMethod::CODE,
        BankTransfer::CODE,
    ];
}
