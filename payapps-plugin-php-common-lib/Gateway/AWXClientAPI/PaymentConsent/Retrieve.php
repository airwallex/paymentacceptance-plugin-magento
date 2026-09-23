<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent;

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
        return 'pa/payment_consents/' . $this->id;
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
    public function setPaymentConsentId(string $id): self
    {
        $this->id = $id;
        return $this;
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
