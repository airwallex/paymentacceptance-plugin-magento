<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Authentication extends AbstractClient
{
    public const X_API_VERSION = '2024-06-30';

    /**
     * @return string[]
     */
    protected function getHeaders(): array
    {
        return [
            'x-client-id' => $this->configuration->getClientId(),
            'x-api-key' => $this->configuration->getApiKey(),
            'x-api-version' => self::X_API_VERSION
        ];
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'authentication/login';
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
