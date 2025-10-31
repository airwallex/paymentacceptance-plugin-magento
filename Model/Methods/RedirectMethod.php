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

    public static function displayNames(): array
    {
        return [
            self::AFTERPAY_CODE => __('Afterpay'),
            self::ALIPAYCN_CODE => __('Alipay CN'),
            self::ALIPAYHK_CODE => __('Alipay HK'),
            self::BANK_TRANSFER_CODE => __('Bank Transfer'),
            self::DANA_CODE => __('DANA'),
            self::GCASH_CODE => __('GCash'),
            self::KAKAO_CODE => __('Kakao Pay'),
            self::KLARNA_CODE => __('Klarna'),
            self::TOUCH_N_GO_CODE => __("Touch 'n Go"),
            self::PAY_NOW_CODE => __('PayNow'),
            self::WECHAT_CODE => __('WeChat Pay'),
        ];
    }
}
