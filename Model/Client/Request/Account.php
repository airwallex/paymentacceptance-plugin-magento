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
        return str_replace('pci-', '', $this->configuration->getApiUrl());
    }

    protected function parseResponse(ResponseInterface $response): string
    {
        return $response->getBody();
    }
}
