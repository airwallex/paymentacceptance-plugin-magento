<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
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
