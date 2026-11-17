<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Retrieve extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . UrlHelper::pathSegment($this->id);
    }

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param string $id
     *
     * @return Retrieve
     */
    public function setPaymentIntentId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param $response
     *
     * @return PaymentIntent
     */
    protected function parseResponse($response): PaymentIntent
    {
        return new PaymentIntent(json_decode((string)$response->getBody(), true));
    }
}
