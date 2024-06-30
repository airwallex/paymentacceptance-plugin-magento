<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;

class CardMethod extends AbstractMethod
{
    public const CODE = 'airwallex_payments_card';

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws LocalizedException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        if ($amount <= 0) {
            return $this;
        }

        if (!$intentId = $this->getIntentId()) {
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }

        $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
        $respArr = json_decode($resp, true);
        if (empty($respArr['status'])) {
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }

        $this->setTransactionId($payment);
        
        // capture in frontend element will run here too, but can not go inside
        $order = $this->paymentIntentRepository->getOrder($intentId);
        if ($respArr['status'] !== PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE) {
            return $this;
        }

        try {
            $result = $this->capture->setPaymentIntentId($intentId)->setInformation($order->getGrandTotal())->send();
            $this->getInfoInstance()->setAdditionalInformation('intent_status', $result->status);
        } catch (GuzzleException $exception) {
            $this->logger->orderError($order, 'capture', $exception->getMessage());
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getConfigPaymentAction(): string
    {
        return $this->getConfigData('airwallex_payment_action');
    }
}
