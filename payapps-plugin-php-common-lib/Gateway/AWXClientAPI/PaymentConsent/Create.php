<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent;

class Create extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents/create';
    }

    /**
     * @param string $id
     *
     * @return Create
     */
    public function setCustomerId(string $id): self
    {
        return $this->setParam('customer_id', $id);
    }

    /**
     * @param string $triggeredBy
     *
     * @return Create
     */
    public function setNextTriggeredBy(string $triggeredBy): Create
    {
        return $this->setParam('next_triggered_by', $triggeredBy);
    }

    /**
     * @param string $merchantTriggerReason
     *
     * @return Create
     */
    public function setMerchantTriggerReason(string $merchantTriggerReason): Create
    {
        return $this->setParam('merchant_trigger_reason', $merchantTriggerReason);
    }

    /**
     * @param $response
     *
     * @return PaymentConsent
     */
    protected function parseResponse($response): PaymentConsent
    {
        return new PaymentConsent(json_decode((string)$response->getBody(), true));
    }
}
