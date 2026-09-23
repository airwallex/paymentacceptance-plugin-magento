<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethod;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethod;

class Create extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_methods/create';
    }

    /**
     * @param array $applepay
     *
     * @return Create
     */
    public function setApplepay(array $applepay): Create
    {
        return $this->setParam('applepay', $applepay);
    }

    /**
     * @param array $card
     *
     * @return Create
     */
    public function setCard(array $card): Create
    {
        return $this->setParam('card', $card);
    }

    /**
     * @param string $customerId
     *
     * @return Create
     */
    public function setCustomerId(string $customerId): Create
    {
        return $this->setParam('customer_id', $customerId);
    }

    /**
     * @param array $googlepay
     *
     * @return Create
     */
    public function setGooglepay(array $googlepay): Create
    {
        return $this->setParam('googlepay', $googlepay);
    }

    /**
     * @param string $type
     *
     * @return Create
     */
    public function setType(string $type): Create
    {
        return $this->setParam('type', $type);
    }

    /**
     * @param $response
     *
     * @return PaymentMethod
     */
    protected function parseResponse($response): PaymentMethod
    {
        return new PaymentMethod(json_decode((string)$response->getBody(), true));
    }
}