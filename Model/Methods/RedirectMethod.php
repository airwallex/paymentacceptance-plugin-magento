<?php

namespace Airwallex\Payments\Model\Methods;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Quote\Api\Data\CartInterface;

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

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     * @throws GuzzleException|JsonException
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isMobileDetectInstalled();
    }
}
