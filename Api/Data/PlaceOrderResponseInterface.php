<?php

namespace Airwallex\Payments\Api\Data;

interface PlaceOrderResponseInterface
{
    public const DATA_KEY_INTENT_ID = 'intent_id';
    public const DATA_KEY_CLIENT_SECRET = 'client_secret';
    public const DATA_KEY_NEXT_ACTION = 'next_action';
    public const DATA_KEY_RESPONSE_TYPE = 'response_type';
    public const DATA_KEY_ORDER_ID = 'order_id';
    public const DATA_KEY_MESSAGE = 'message';

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
}
