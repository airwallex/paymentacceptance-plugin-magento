<?php

namespace Airwallex\Payments\Api\Data;

interface ClientSecretResponseInterface
{
    public const DATA_KEY_CLIENT_SECRET = 'client_secret';
    public const DATA_KEY_EXPIRED_TIME = 'expired_time';
    public const DATA_KEY_CUSTOMER_ID = 'customer_id';

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string;

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setClientSecret(string $secret = null): ClientSecretResponseInterface;

    /**
     * @return string|null
     */
    public function getExpiredTime(): ?string;

    /**
     * @param string|null $time
     * @return $this
     */
    public function setExpiredTime(string $time = null): ClientSecretResponseInterface;

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string;
}
