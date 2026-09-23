<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay\Domain;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ApplePayDomains;

class GetList extends AbstractApi
{
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
        return 'pa/config/applepay/registered_domains';
    }

    /**
     * @param $response
     *
     * @return ApplePayDomains
     */
    protected function parseResponse($response): ApplePayDomains
    {
        return new ApplePayDomains(json_decode((string)$response->getBody(), true));
    }
}