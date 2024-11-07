<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class CurrencySwitcher extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/quotes/create';
    }

    /**
     * @param string $currency
     * @return CurrencySwitcher
     */
    public function setPaymentCurrency(string $currency): AbstractClient
    {
        return $this->setParam('payment_currency', $currency);
    }

    /**
     * @param string $currency
     * @return CurrencySwitcher
     */
    public function setTargetCurrency(string $currency): AbstractClient
    {
        return $this->setParam('target_currency', $currency);
    }

    /**
     * @param float $amount
     * @return CurrencySwitcher
     */
    public function setAmount(float $amount): AbstractClient
    {
        return $this->setParam('payment_amount', $amount);
    }

    /**
     * @return CurrencySwitcher
     */
    public function setType(): AbstractClient
    {
        return $this->setParam('type', 'currency_switcher');
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     */
    protected function parseResponse(ResponseInterface $response): string
    {
        return $response->getBody();
    }
}
