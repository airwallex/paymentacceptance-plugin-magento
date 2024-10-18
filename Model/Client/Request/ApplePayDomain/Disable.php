<?php

namespace Airwallex\Payments\Model\Client\Request\ApplePayDomain;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Disable extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @param string $domain
     * @return $this
     */
    public function setDomain(string $domain): Disable
    {
        $params = [
            'items' => [$domain]
        ];
        return $this->setParams($params);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/config/applepay/registered_domains/remove_items';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        return $this->parseJson($response)->items ?? [];
    }
}