<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\ConversionQuote as GatewayConversionQuote;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ConversionQuote as StructConversionQuote;
use Exception;

class ConversionQuote
{
    use CacheTrait;

    /**
     * @var string
     */
    private $merchantCurrency;

    /**
     * @var string
     */
    private $shopperCurrency;

    /**
     * @param string $merchantCurrency
     *
     * @return self
     */
    public function setMerchantCurrency(string $merchantCurrency): self
    {
        $this->merchantCurrency = $merchantCurrency;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantCurrency(): string
    {
        return $this->merchantCurrency ?? '';
    }

    /**
     * @param string $shopperCurrency
     *
     * @return self
     */
    public function setShopperCurrency(string $shopperCurrency): self
    {
        $this->shopperCurrency = $shopperCurrency;
        return $this;
    }

    /**
     * @return string
     */
    public function getShopperCurrency(): string
    {
        return $this->shopperCurrency ?? '';
    }

    /**
     * @return StructConversionQuote
     * @throws Exception
     */
    public function get(): StructConversionQuote
    {
        $cacheName = "awx_conversion_quote"
                . '_' . $this->getMerchantCurrency()
                . '_' . $this->getShopperCurrency();
        return $this->cacheRemember(
            $cacheName,
            function () {
                return $this->createGateway()->setMerchantCurrency($this->merchantCurrency)
                    ->setShopperCurrency($this->shopperCurrency)
                    ->send();
            },
            1800
        );
    }

    /**
     * @return GatewayConversionQuote
     */
    protected function createGateway(): GatewayConversionQuote
    {
        return new GatewayConversionQuote();
    }
}
