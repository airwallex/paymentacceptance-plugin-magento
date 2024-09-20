<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class AvailablePaymentMethods extends AbstractClient implements BearerAuthenticationInterface
{
    public const TRANSACTION_MODE = 'oneoff';

    public string $cacheName = 'available_payment_method_types';

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): self
    {
        if (empty($currency)) return $this;
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

    public function setTransactionMode(string $mode): self
    {
        return $this->setParam('transaction_mode', $mode);
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
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        if (empty($content)) return [];
        $content = json_decode($content, true);
        return $content['items'] ?? [];
    }
}
