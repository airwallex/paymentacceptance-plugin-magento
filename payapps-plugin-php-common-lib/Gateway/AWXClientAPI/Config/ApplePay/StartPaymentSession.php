<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;

class StartPaymentSession extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_session/start';
    }

    /**
     * @param array $initiativeParams
     *
     * @return StartPaymentSession
     */
    public function setInitiativeParams(array $initiativeParams): StartPaymentSession
    {
        $this->setParams($initiativeParams);
        return $this;
    }

    /**
     * @param $response
     * @return string
     */
    protected function parseResponse($response): string
    {
        return (string)$response->getBody();
    }
}
