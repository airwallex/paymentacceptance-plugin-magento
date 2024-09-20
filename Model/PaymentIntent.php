<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as ResourcePaymentIntent;

class PaymentIntent extends AbstractModel implements IdentityInterface, PaymentIntentInterface
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourcePaymentIntent::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return ['airwallex_payment_intents' . '_' . $this->getId()];
    }

    /**
     * @return string
     */
    public function getOrderIncrementId(): string
    {
        return $this->getData(PaymentIntentInterface::ORDER_INCREMENT_ID_COLUMN);
    }

    /**
     * @return string
     */
    public function getIntentId(): string
    {
        return $this->getData(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN);
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->getData(PaymentIntentInterface::CURRENCY_CODE_COLUMN);
    }

    /**
     * @return float
     */
    public function getGrandTotal(): float
    {
        return $this->getData(PaymentIntentInterface::GRAND_TOTAL_COLUMN);
    }

    /**
     * @return int
     */
    public function getQuoteId(): int
    {
        return $this->getData(PaymentIntentInterface::QUOTE_ID_COLUMN);
    }

    /**
     * @return int
     */
    public function getOrderId(): int
    {
        return $this->getData(PaymentIntentInterface::ORDER_ID_COLUMN);
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->getData(PaymentIntentInterface::STORE_ID_COLUMN);
    }

    /**
     * @return ?string
     */
    public function getDetail(): ?string
    {
        return $this->getData(PaymentIntentInterface::DETAIL_COLUMN);
    }

    /**
     * @param string $orderIncrementId
     *
     * @return PaymentIntentInterface
     */
    public function setOrderIncrementId(string $orderIncrementId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::ORDER_INCREMENT_ID_COLUMN, $orderIncrementId);
    }

    /**
     * @param string $paymentIntentId
     *
     * @return PaymentIntentInterface
     */
    public function setPaymentIntentId(string $paymentIntentId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN, $paymentIntentId);
    }

    /**
     * @param string $currencyCode
     *
     * @return PaymentIntentInterface
     */
    public function setCurrencyCode(string $currencyCode): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::CURRENCY_CODE_COLUMN, $currencyCode);
    }

    /**
     * @param float $grandTotal
     *
     * @return PaymentIntentInterface
     */
    public function setGrandTotal(float $grandTotal): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::GRAND_TOTAL_COLUMN, $grandTotal);
    }

    /**
     * @param int $quoteId
     *
     * @return PaymentIntentInterface
     */
    public function setQuoteId(int $quoteId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::QUOTE_ID_COLUMN, $quoteId);
    }

    /**
     * @param int $orderId
     *
     * @return PaymentIntentInterface
     */
    public function setOrderId(int $orderId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::ORDER_ID_COLUMN, $orderId);
    }

    /**
     * @param int $storeId
     *
     * @return PaymentIntentInterface
     */
    public function setStoreId(int $storeId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::STORE_ID_COLUMN, $storeId);
    }

    /**
     * @param string $detail
     *
     * @return PaymentIntentInterface
     */
    public function setDetail(string $detail): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::DETAIL_COLUMN, $detail);
    }
}
