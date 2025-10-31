<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay\Domain;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ApplePayDomains;

class RemoveItems extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/config/applepay/registered_domains/remove_items';
    }

    /**
     * @param array $items
     *
     * @return RemoveItems
     */
    public function setItems(array $items): RemoveItems
    {
        return $this->setParam('items', $items);
    }

    /**
     * @param string $reason
     *
     * @return RemoveItems
     */
    public function setReason(string $reason): RemoveItems
    {
        return $this->setParam('reason', $reason);
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
