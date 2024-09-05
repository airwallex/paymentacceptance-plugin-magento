<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;

class CardMethod extends AbstractMethod
{
    public const CODE = 'airwallex_payments_card';

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws JsonException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        if ($amount <= 0) return $this;

        /** @var Payment $payment */
        $order = $payment->getOrder();
        $intentId = $this->getIntentId($payment);

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
        try {
            $this->capture->setPaymentIntentId($intentId)->setInformation($order->getGrandTotal())->send();
        } catch (GuzzleException $exception) {
            $this->logger->orderError($order, 'capture', $exception->getMessage());
            throw $exception;
        }
        return $this;
    }
}
