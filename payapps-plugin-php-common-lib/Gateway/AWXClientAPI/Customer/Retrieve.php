<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Customer;

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
        return 'pa/customers/' . $this->id;
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
    public function setCustomerId(string $id): self
    {
        $this->id = $id;
        return $this;
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