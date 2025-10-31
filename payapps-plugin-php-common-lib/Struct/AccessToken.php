<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class AccessToken extends AbstractBase
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $expiresAt;

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token ?? '';
    }

    /**
     * @param string $token
     *
     * @return AccessToken
     */
    public function setToken(string $token): AccessToken
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt ?? '';
    }

    /**
     * @param string $expiresAt
     *
     * @return AccessToken
     */
    public function setExpiresAt(string $expiresAt): AccessToken
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
