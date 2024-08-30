<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\Client\AbstractClient;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment;
use Mobile_Detect;
use Exception;

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
     * @throws GuzzleException
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isMobileDetectInstalled();
    }

    public function getConfigPaymentAction(): string
    {
        if (!$this->isOrderCreatedHelper->isCreated()) return '';
        $intent = $this->intentHelper->getIntent();
        if ($intent['status'] ===  PaymentIntentInterface::INTENT_STATUS_SUCCEEDED) {
            return MethodInterface::ACTION_AUTHORIZE_CAPTURE;
        }
        return MethodInterface::ACTION_AUTHORIZE;
    }
}
