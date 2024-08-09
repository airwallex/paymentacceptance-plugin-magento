<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Model\Client\AbstractClient;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
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
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws GuzzleException
     * @throws AlreadyExistsException
     * @throws LocalizedException|JsonException
     */
    public function authorize(InfoInterface $payment, $amount): self
    {
        $cacheName = AbstractClient::METADATA_PAYMENT_METHOD_PREFIX . $this->checkoutHelper->getQuote()->getEntityId();
        /** @var Payment $payment */
        $this->cache->save($payment->getMethod(), $cacheName, [], 60);
        $intendResponse = $this->paymentIntents->getIntent();
        $returnUrl = $this->getAirwallexPaymentsRedirectUrl($intendResponse['id']);
        $this->checkoutHelper->getCheckout()->setAirwallexPaymentsRedirectUrl($returnUrl);
        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws LocalizedException
     */
    public function getAirwallexPaymentsRedirectUrl($intentId)
    {
        $detect = new Mobile_Detect();
        try {
            $returnUrl = $this->confirm
                ->setPaymentIntentId($intentId)
                ->setInformation($this->getPaymentMethodCode(), $detect->isMobile(), $this->getMobileOS($detect))
                ->send();
        } catch (Exception $exception) {
            throw new LocalizedException(__($exception->getMessage()));
        }

        return $returnUrl;
    }

    /**
     * @param Mobile_Detect $detect
     *
     * @return string|null
     */
    private function getMobileOS(Mobile_Detect $detect): ?string
    {
        if (!$detect->isMobile()) {
            return null;
        }

        return $detect->isAndroidOS() ? 'android' : 'ios';
    }

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isMobileDetectInstalled();
    }
}
