<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\CustomerClientSecret;

class GenerateClientSecret extends AbstractApi
{
    /**
     * @var string
     */
    private $customerId;

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
        return 'pa/customers/' . $this->customerId . '/generate_client_secret';
    }

    /**
     * @param string $customerId
     *
     * @return GenerateClientSecret
     */
    public function setCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @param $response
     * @return CustomerClientSecret
     */
    protected function parseResponse($response): CustomerClientSecret
    {
        return new CustomerClientSecret(json_decode((string)$response->getBody(), true));
    }
}