<?php

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
        return $this->getData(self::DATA_KEY_INTENT_ID);
    }

    /**
     * @param string|null $intentId
     * @return PlaceOrderResponse
     */
    public function setIntentId(string $intentId = null): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_INTENT_ID, $intentId);
    }

    /**
     * @return string|null
     */
    public function getRedirectUrl(): ?string
    {
        return $this->getData(self::DATA_KEY_REDIRECT_URL);
    }

    /**
     * @param string|null $redirectUrl
     * @return PlaceOrderResponse
     */
    public function setRedirectUrl(string $redirectUrl = null): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_INTENT_ID, $redirectUrl);
    }

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return $this->getData(self::DATA_KEY_CLIENT_SECRET);
    }

    /**
     * @param string|null $clientSecret
     * @return PlaceOrderResponse
     */
    public function setClientSecret(string $clientSecret = null): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_CLIENT_SECRET, $clientSecret);
    }

    /**
     * @return string
     */
    public function getResponseType(): string
    {
        return $this->getData(self::DATA_KEY_RESPONSE_TYPE);
    }

    /**
     * @param string $responseType
     * @return PlaceOrderResponse
     */
    public function setResponseType(string $responseType): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_RESPONSE_TYPE, $responseType);
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->getData(self::DATA_KEY_MESSAGE);
    }

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_MESSAGE, $message);
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::DATA_KEY_ORDER_ID);
    }

    /**
     * @param int|null $orderId
     * @return PlaceOrderResponse
     */
    public function setOrderId(int $orderId = null): PlaceOrderResponse
    {
        return $this->setData(self::DATA_KEY_ORDER_ID, $orderId);
    }
}
