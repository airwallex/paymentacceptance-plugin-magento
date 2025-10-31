<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethod;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethod;

class Get extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_methods/' . $this->id;
    }

    /**
     * @param string $paymentMethodId
     *
     * @return Get
     */
    public function setPaymentMethodId(string $paymentMethodId): Get
    {
        $this->id = $paymentMethodId;
        return $this;
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