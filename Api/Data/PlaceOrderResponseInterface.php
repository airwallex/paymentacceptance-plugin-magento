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

interface PlaceOrderResponseInterface
{
    public const DATA_KEY_INTENT_ID = 'intent_id';
    public const DATA_KEY_CLIENT_SECRET = 'client_secret';
    public const DATA_KEY_NEXT_ACTION = 'next_action';
    public const DATA_KEY_RESPONSE_TYPE = 'response_type';
    public const DATA_KEY_ORDER_ID = 'order_id';
    public const DATA_KEY_MESSAGE = 'message';
    public const DATA_KEY_ELEMENT_OPTIONS = 'element_options';

    /**
     * @return string|null
     */
    public function getIntentId(): ?string;

    /**
     * @param string|null $intentId
     * @return $this
     */
    public function setIntentId(?string $intentId = null): PlaceOrderResponseInterface;

    /**
     * @return string|null
     */
    public function getNextAction(): ?string;

    /**
     * @param string|null $nextAction
     * @return $this
     */
    public function setNextAction(?string $nextAction = null): PlaceOrderResponseInterface;

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string;

    /**
     * @param string|null $clientSecret
     * @return $this
     */
    public function setClientSecret(?string $clientSecret = null): PlaceOrderResponseInterface;

    /**
     * @return string|null
     */
    public function getResponseType(): ?string;

    /**
     * @param string $responseType
     * @return $this
     */
    public function setResponseType(string $responseType): PlaceOrderResponseInterface;

    /**
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): PlaceOrderResponseInterface;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId = null): PlaceOrderResponseInterface;

    /**
     * Get element options as JSON string
     *
     * @return string|null
     */
    public function getElementOptions(): ?string;

    /**
     * Set element options as JSON string
     *
     * @param string|null $elementOptions
     * @return $this
     */
    public function setElementOptions(?string $elementOptions = null): PlaceOrderResponseInterface;
}
