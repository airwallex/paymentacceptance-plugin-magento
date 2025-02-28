<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;

class CardMethod extends AbstractMethod
{
    public const CODE = 'airwallex_payments_card';

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return CardMethod $this
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        if ($amount <= 0) return $this;

        $order = $payment->getOrder();
        if ($order && $order->getId()) {
            /** @var Payment $payment */
            $intentId = $this->getIntentId($payment);
        } else {
            $intentResponse = $this->intentHelper->getIntent();
            /** @var Payment $payment */
            $this->setTransactionId($payment, $intentResponse['id']);
            $intentId = $intentResponse['id'];
        }

        $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
        $respArr = json_decode($resp, true);
        if (empty($respArr['status'])) {
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }

        // capture in frontend element will run here too, but can not go inside
        if ($respArr['status'] !== PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE) {
            return $this;
        }

        $this->cache->save(true, $this->captureCacheName($intentId), [], 3600);
        $this->capture->setPaymentIntentId($intentId)->setInformation($respArr['amount'])->send();
        return $this;
    }
}
