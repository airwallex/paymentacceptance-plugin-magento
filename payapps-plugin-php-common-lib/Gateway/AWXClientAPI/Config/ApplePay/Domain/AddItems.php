<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay\Domain;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ApplePayDomains;

class AddItems extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/config/applepay/registered_domains/add_items';
    }

    /**
     * @param array $items
     *
     * @return AddItems
     */
    public function setItems(array $items): AddItems
    {
        return $this->setParam('items', $items);
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