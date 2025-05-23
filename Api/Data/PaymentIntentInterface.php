<?php

namespace Airwallex\Payments\Api\Data;

interface PaymentIntentInterface
{
    public const TABLE = 'airwallex_payment_intents';
    public const ID_COLUMN = 'id';
    public const ORDER_INCREMENT_ID_COLUMN = 'order_increment_id';
    public const PAYMENT_INTENT_ID_COLUMN = 'payment_intent_id';
    public const CURRENCY_CODE_COLUMN = 'currency_code';
    public const GRAND_TOTAL_COLUMN = 'grand_total';
    public const SWITCHER_CURRENCY_CODE_COLUMN = 'switcher_currency_code';
    public const SWITCHER_GRAND_TOTAL_COLUMN = 'switcher_grand_total';
    public const QUOTE_ID_COLUMN = 'quote_id';
    public const ORDER_ID_COLUMN = 'order_id';
    public const STORE_ID_COLUMN = 'store_id';
    public const DETAIL_COLUMN = 'detail';
    public const METHOD_CODES_COLUMN = 'method_codes';
    public const INTENT_STATUS_REQUIRES_CAPTURE = 'REQUIRES_CAPTURE';
    public const INTENT_STATUS_SUCCEEDED = 'SUCCEEDED';

    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * @return string
     */
    public function getIntentId(): string;

    /**
     * @return string
     */
    public function getCurrencyCode(): string;

    /**
     * @return float
     */
    public function getGrandTotal(): float;

    /**
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @return ?string
     */
    public function getDetail(): ?string;

    /**
     * @return string
     */
    public function getMethodCodes(): string;

    /**
     * @param string $orderIncrementId
     *
     * @return $this
     */
    public function setOrderIncrementId(string $orderIncrementId): self;

    /**
     * @param string $paymentIntentId
     *
     * @return $this
     */
    public function setPaymentIntentId(string $paymentIntentId): self;

    /**
     * @param string $currencyCode
     *
     * @return $this
     */
    public function setCurrencyCode(string $currencyCode): self;

    /**
     * @param float $grandTotal
     *
     * @return $this
     */
    public function setGrandTotal(float $grandTotal): self;

    /**
     * @param string $currencyCode
     *
     * @return $this
     */
    public function setSwitcherCurrencyCode(string $currencyCode): self;

    /**
     * @param float $grandTotal
     *
     * @return $this
     */
    public function setSwitcherGrandTotal(float $grandTotal): self;

    /**
     * @param int $quoteId
     *
     * @return $this
     */
    public function setQuoteId(int $quoteId): self;

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * @param int $storeId
     *
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * @param string $detail
     *
     * @return $this
     */
    public function setDetail(string $detail): self;

    /**
     * @param string $codes
     *
     * @return $this
     */
    public function setMethodCodes(string $codes): self;
}
