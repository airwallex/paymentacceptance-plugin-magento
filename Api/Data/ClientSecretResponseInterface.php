<?php

namespace Airwallex\Payments\Api\Data;

interface ClientSecretResponseInterface
{
    public const DATA_KEY_CLIENT_SECRET = 'client_secret';
    public const DATA_KEY_EXPIRED_TIME = 'expired_time';

    /**
     * @return string|null
     */
    public function getClientSecret();

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setClientSecret(string $secret = null);

    /**
     * @return string|null
     */
    public function getExpiredTime();

    /**
     * @param string|null $time
     * @return $this
     */
    public function setExpiredTime(string $time = null);
}
