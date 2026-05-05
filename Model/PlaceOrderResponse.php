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
namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Magento\Framework\DataObject;

class PlaceOrderResponse extends DataObject implements PlaceOrderResponseInterface
{
    /**
     * @return string|null
     */
    public function getIntentId(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_INTENT_ID);
    }

    /**
     * @param string|null $intentId
     * @return PlaceOrderResponse
     */
    public function setIntentId(?string $intentId = null): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_INTENT_ID, $intentId);
    }

    /**
     * @return string|null
     */
    public function getNextAction(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_NEXT_ACTION);
    }

    /**
     * @param string|null $nextAction
     * @return PlaceOrderResponse
     */
    public function setNextAction(?string $nextAction = null): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_NEXT_ACTION, $nextAction);
    }

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_CLIENT_SECRET);
    }

    /**
     * @param string|null $clientSecret
     * @return PlaceOrderResponse
     */
    public function setClientSecret(?string $clientSecret = null): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_CLIENT_SECRET, $clientSecret);
    }

    /**
     * @return string|null
     */
    public function getResponseType(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_RESPONSE_TYPE);
    }

    /**
     * @param string $responseType
     * @return PlaceOrderResponse
     */
    public function setResponseType(string $responseType): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_RESPONSE_TYPE, $responseType);
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_MESSAGE);
    }

    /**
     * @param string $message
     * @return PlaceOrderResponse
     */
    public function setMessage(string $message): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_MESSAGE, $message);
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_ORDER_ID);
    }

    /**
     * @param int|null $orderId
     * @return PlaceOrderResponse
     */
    public function setOrderId(?int $orderId = null): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_ORDER_ID, $orderId);
    }

    public function getElementOptions(): ?string
    {
        return $this->getData(PlaceOrderResponseInterface::DATA_KEY_ELEMENT_OPTIONS);
    }

    public function setElementOptions(?string $elementOptions = null): PlaceOrderResponse
    {
        return $this->setData(PlaceOrderResponseInterface::DATA_KEY_ELEMENT_OPTIONS, $elementOptions);
    }
}
