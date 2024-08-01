<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\ClientSecretResponseInterface;
use Magento\Framework\DataObject;

class ClientSecretResponse extends DataObject implements ClientSecretResponseInterface
{
    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return $this->getData(self::DATA_KEY_CLIENT_SECRET);
    }

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setClientSecret(string $secret = null): ClientSecretResponse
    {
        return $this->setData(self::DATA_KEY_CLIENT_SECRET, $secret);
    }

    /**
     * @return string|null
     */
    public function getExpiredTime(): ?string
    {
        return $this->getData(self::DATA_KEY_EXPIRED_TIME);
    }

    /**
     * @param string|null $time
     * @return $this
     */
    public function setExpiredTime(string $time = null): ClientSecretResponse
    {
        return $this->setData(self::DATA_KEY_EXPIRED_TIME, $time);
    }
}
