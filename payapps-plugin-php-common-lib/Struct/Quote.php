<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Quote extends AbstractBase
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $paymentCurrency;

    /**
     * @var string
     */
    private $currencyPair;

    /**
     * @var float
     */
    private $clientRate;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $validFrom;

    /**
     * @var string
     */
    private $validTo;

    /**
     * @var string
     */
    private $targetCurrency;

    /**
     * @var float
     */
    private $paymentAmount;

    /**
     * @var float
     */
    private $targetAmount;

    /**
     * @var string
     */
    private $refreshAt;

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
     * @return Quote
     */
    public function setId(string $id): Quote
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type ?? '';
    }

    /**
     * @param string $type
     *
     * @return Quote
     */
    public function setType(string $type): Quote
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentCurrency(): string
    {
        return $this->paymentCurrency ?? '';
    }

    /**
     * @param string $paymentCurrency
     *
     * @return Quote
     */
    public function setPaymentCurrency(string $paymentCurrency): Quote
    {
        $this->paymentCurrency = $paymentCurrency;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrencyPair(): string
    {
        return $this->currencyPair ?? '';
    }

    /**
     * @param string $currencyPair
     *
     * @return Quote
     */
    public function setCurrencyPair(string $currencyPair): Quote
    {
        $this->currencyPair = $currencyPair;
        return $this;
    }

    /**
     * @return float
     */
    public function getClientRate(): float
    {
        return $this->clientRate;
    }

    /**
     * @param float $clientRate
     *
     * @return Quote
     */
    public function setClientRate(float $clientRate): Quote
    {
        $this->clientRate = $clientRate;
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
     * @return Quote
     */
    public function setCreatedAt(string $createdAt): Quote
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getValidFrom(): string
    {
        return $this->validFrom ?? '';
    }

    /**
     * @param string $validFrom
     *
     * @return Quote
     */
    public function setValidFrom(string $validFrom): Quote
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    /**
     * @return string
     */
    public function getValidTo(): string
    {
        return $this->validTo ?? '';
    }

    /**
     * @param string $validTo
     *
     * @return Quote
     */
    public function setValidTo(string $validTo): Quote
    {
        $this->validTo = $validTo;
        return $this;
    }

    /**
     * @return string
     */
    public function getTargetCurrency(): string
    {
        return $this->targetCurrency ?? '';
    }

    /**
     * @param string $targetCurrency
     *
     * @return Quote
     */
    public function setTargetCurrency(string $targetCurrency): Quote
    {
        $this->targetCurrency = $targetCurrency;
        return $this;
    }

    /**
     * @return float
     */
    public function getPaymentAmount(): float
    {
        return $this->paymentAmount;
    }

    /**
     * @param float $paymentAmount
     *
     * @return Quote
     */
    public function setPaymentAmount(float $paymentAmount): Quote
    {
        $this->paymentAmount = $paymentAmount;
        return $this;
    }

    /**
     * @return float
     */
    public function getTargetAmount(): float
    {
        return $this->targetAmount;
    }

    /**
     * @param float $targetAmount
     *
     * @return Quote
     */
    public function setTargetAmount(float $targetAmount): Quote
    {
        $this->targetAmount = $targetAmount;
        return $this;
    }

    /**
     * @return string
     */
    public function getRefreshAt(): string
    {
        return $this->refreshAt ?? '';
    }

    /**
     * @param string $refreshAt
     *
     * @return Quote
     */
    public function setRefreshAt(string $refreshAt): Quote
    {
        $this->refreshAt = $refreshAt;
        return $this;
    }
}
