<?php
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
    public const IDEAL_CODE = 'airwallex_payments_ideal';

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
            self::IDEAL_CODE => __('iDEAL'),
        ];
    }
}
