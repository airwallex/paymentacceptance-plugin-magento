<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class AvailablePaymentMethods extends AbstractClient implements BearerAuthenticationInterface
{
    private const TRANSACTION_MODE = 'oneoff';

    public string $cacheName = 'available_payment_method_types';

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): self
    {
        return $this->setParam('transaction_currency', $currency);
    }

    public function setResources(): self
    {
        return $this->setParam('__resources', 'true');
    }

    public function setActive(): self
    {
        return $this->setParam('active', 'true');
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/config/payment_method_types';
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $this->cache->save(
            $response->getBody(),
            $this->cacheName,
            [],
            60
        );

        $response = $this->parseJson($response);

        return array_map(static function (object $item) {
            return $item->name;
        }, $response->items);
    }
}
