<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class RetrieveCustomer extends AbstractClient implements BearerAuthenticationInterface
{
    private string $id;

    /**
     * @param string $id
     * @return $this
     */
    public function setAirwallexCustomerId(string $id): RetrieveCustomer
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/customers/' . $this->id;
    }


    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @param ResponseInterface $response
     *
     * @return object
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): object
    {
        return $this->parseJson($response);
    }
}
