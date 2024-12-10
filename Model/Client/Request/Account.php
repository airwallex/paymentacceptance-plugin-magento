<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class Account extends AbstractClient implements BearerAuthenticationInterface
{
    protected function getMethod(): string
    {
        return "GET";
    }

    protected function getUri(): string
    {
        return 'account';
    }

    protected function getBaseUrl(): string
    {
        $url = 'https://www.airwallex.com/payment_app/plugin/api/v1/';
        if ($this->configuration->isDemoMode()) {
            $url = 'https://demo.airwallex.com/payment_app/plugin/api/v1/';
        }
        return $url;
    }

    protected function parseResponse(ResponseInterface $response): string
    {
        return $response->getBody();
    }
}
