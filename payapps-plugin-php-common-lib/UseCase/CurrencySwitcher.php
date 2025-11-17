<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Quote;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Quote as StructQuote;
use Exception;

class CurrencySwitcher
{
    use CacheTrait;

    /**
     * @var string
     */
    private $paymentCurrency;

    /**
     * @var string
     */
    private $targetCurrency;

    /**
     * @var float
     */
    private $paymentAmount;

    /**
     * @var string
     */
    private $transactionId;

    /**
     * @param float $paymentAmount
     *
     * @return self
     */
    public function setPaymentAmount(float $paymentAmount): self
    {
        $this->paymentAmount = $paymentAmount;
        return $this;
    }
    
    /**
     * @return float
     */
    public function getPaymentAmount(): float
    {
        return $this->paymentAmount ?? 0.0;
    }

    /**
     * @param string $paymentCurrency
     *
     * @return self
     */
    public function setPaymentCurrency(string $paymentCurrency): self
    {
        $this->paymentCurrency = $paymentCurrency;
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
     * @param string $targetCurrency
     *
     * @return self
     */
    public function setTargetCurrency(string $targetCurrency): self
    {
        $this->targetCurrency = $targetCurrency;
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
     * @param string $transactionId
     *
     * @return self
     */
    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId ?? '';
    }

    /**
     * @return StructQuote
     * @throws Exception
     */
    public function get(): StructQuote
    {
        $cacheName = "awx_currency_switcher"
                . '_' . $this->getTargetCurrency()
                . '_' . $this->getPaymentCurrency()
                . '_' . $this->getPaymentAmount()
                . '_' . $this->getTransactionId();
        return $this->cacheRemember(
            $cacheName,
            function () {
                return (new Quote)->setPaymentCurrency($this->paymentCurrency)
                    ->setTargetCurrency($this->targetCurrency)
                    ->setPaymentAmount($this->paymentAmount)
                    ->setType('currency_switcher')
                    ->send();
            }
        );
    }
}