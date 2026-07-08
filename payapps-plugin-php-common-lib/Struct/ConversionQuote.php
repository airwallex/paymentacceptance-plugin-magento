<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class ConversionQuote extends AbstractBase
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $merchantCurrency;

    /**
     * @var string
     */
    private $shopperCurrency;

    /**
     * @var float
     */
    private $conversionRate;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $expiresAt;

    /**
     * @var string
     */
    private $validityPeriod;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id ?? '';
    }

    /**
     * @param string $id
     *
     * @return ConversionQuote
     */
    public function setId(string $id): ConversionQuote
    {
        $this->id = $id;
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
     * @param string $merchantCurrency
     *
     * @return ConversionQuote
     */
    public function setMerchantCurrency(string $merchantCurrency): ConversionQuote
    {
        $this->merchantCurrency = $merchantCurrency;
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
     * @param string $shopperCurrency
     *
     * @return ConversionQuote
     */
    public function setShopperCurrency(string $shopperCurrency): ConversionQuote
    {
        $this->shopperCurrency = $shopperCurrency;
        return $this;
    }

    /**
     * @return float
     */
    public function getConversionRate(): float
    {
        return $this->conversionRate ?? 0.0;
    }

    /**
     * @param float $conversionRate
     *
     * @return ConversionQuote
     */
    public function setConversionRate(float $conversionRate): ConversionQuote
    {
        $this->conversionRate = $conversionRate;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status ?? '';
    }

    /**
     * @param string $status
     *
     * @return ConversionQuote
     */
    public function setStatus(string $status): ConversionQuote
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt ?? '';
    }

    /**
     * @param string $createdAt
     *
     * @return ConversionQuote
     */
    public function setCreatedAt(string $createdAt): ConversionQuote
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt ?? '';
    }

    /**
     * @param string $expiresAt
     *
     * @return ConversionQuote
     */
    public function setExpiresAt(string $expiresAt): ConversionQuote
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getValidityPeriod(): string
    {
        return $this->validityPeriod ?? '';
    }

    /**
     * @param string $validityPeriod
     *
     * @return ConversionQuote
     */
    public function setValidityPeriod(string $validityPeriod): ConversionQuote
    {
        $this->validityPeriod = $validityPeriod;
        return $this;
    }
}
