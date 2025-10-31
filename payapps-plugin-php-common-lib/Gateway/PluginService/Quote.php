<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Quote as StructQuote;

class Quote extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/quotes/create';
    }

    /**
     * @param string $currency
     *
     * @return Quote
     */
    public function setPaymentCurrency(string $currency): Quote
    {
        return $this->setParam('payment_currency', $currency);
    }

    /**
     * @param string $currency
     *
     * @return Quote
     */
    public function setTargetCurrency(string $currency): Quote
    {
        return $this->setParam('target_currency', $currency);
    }

    /**
     * @param float $amount
     *
     * @return Quote
     */
    public function setPaymentAmount(float $amount): Quote
    {
        return $this->setParam('payment_amount', $amount);
    }

    /**
     * @param string $type
     *
     * @return Quote
     */
    public function setType(string $type): Quote
    {
        return $this->setParam('type', $type);
    }

    /**
     * @param $response
     *
     * @return StructQuote
     */
    protected function parseResponse($response): StructQuote
    {
        return new StructQuote(json_decode((string)$response->getBody(), true));
    }
}
