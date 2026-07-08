<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ConversionQuote as StructConversionQuote;

class ConversionQuote extends AbstractApi
{
    public function __construct()
    {
        // validity_period defaults to HR_1 when not explicitly set.
        $this->setValidityPeriod();
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/conversion_quotes/create';
    }

    /**
     * The period for which the conversion quote remains valid. One of HR_1,
     * HR_24. Defaults to HR_1.
     *
     * @param string $validityPeriod
     *
     * @return ConversionQuote
     */
    public function setValidityPeriod(string $validityPeriod = 'HR_1'): ConversionQuote
    {
        return $this->setParam('validity_period', $validityPeriod);
    }

    /**
     * @param string $currency
     *
     * @return ConversionQuote
     */
    public function setMerchantCurrency(string $currency): ConversionQuote
    {
        return $this->setParam('merchant_currency', $currency);
    }

    /**
     * @param string $currency
     *
     * @return ConversionQuote
     */
    public function setShopperCurrency(string $currency): ConversionQuote
    {
        return $this->setParam('shopper_currency', $currency);
    }

    /**
     * @param $response
     *
     * @return StructConversionQuote
     */
    protected function parseResponse($response): StructConversionQuote
    {
        return new StructConversionQuote(json_decode((string)$response->getBody(), true));
    }
}
