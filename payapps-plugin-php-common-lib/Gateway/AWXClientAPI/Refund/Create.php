<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Refund as StructRefund;

class Create extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/refunds/create';
    }

    /**
     * @param string $paymentIntentId
     *
     * @return self
     */
    public function setPaymentIntentId(string $paymentIntentId): self
    {
        return $this->setParam('payment_intent_id', $paymentIntentId);
    }

    /**
     * @param float $amount
     *
     * @return self
     */
    public function setAmount(float $amount): self
    {
        return $this->setParam('amount', $amount);
    }

    /**
     * @param string $reason
     *
     * @return self
     */
    public function setReason(string $reason): self
    {
        return $this->setParam('reason', $reason);
    }

    /**
     * @param $response
     *
     * @return StructRefund
     */
    protected function parseResponse($response): StructRefund
    {
        return new StructRefund(json_decode((string)$response->getBody(), true));
    }
}
