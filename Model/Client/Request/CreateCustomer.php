<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class CreateCustomer extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/customers/create';
    }

    /**
     * @param string $magentoCustomerId
     * @return AbstractClient|CreateCustomer
     */
    public function setMagentoCustomerId(string $magentoCustomerId)
    {
        return $this->setParam('merchant_customer_id', $magentoCustomerId);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): string
    {
        return $response->getBody();
    }
}
