<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Customer;
use Exception;

class Create extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/customers/create';
    }

    /**
     * @param int|string $platformUserId
     * @return Create
     *
     * @throws Exception
     */
    public function setCustomerId($platformUserId): Create
    {
        $platform = Init::getInstance()->get('plugin_type', '');
        $merchantCustomerId = substr(bin2hex(random_bytes(10)), 0, 20);
        return $this->setParam('merchant_customer_id', $platform . '-' . $platformUserId . '-' . $merchantCustomerId);
    }

    /**
     * @param $response
     *
     * @return Customer
     */
    protected function parseResponse($response): Customer
    {
        return new Customer(json_decode((string)$response->getBody(), true));
    }
}