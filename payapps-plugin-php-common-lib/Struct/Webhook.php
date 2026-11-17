<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Webhook extends AbstractBase
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $version;

    /**
     * @var array
     */
    private $events;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * Only present on the delete response.
     *
     * @var bool
     */
    private $deleted;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id ?? '';
    }

    /**
     * @param string $id
     *
     * @return Webhook
     */
    public function setId(string $id): Webhook
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId ?? '';
    }

    /**
     * @param string $requestId
     *
     * @return Webhook
     */
    public function setRequestId(string $requestId): Webhook
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url ?? '';
    }

    /**
     * @param string $url
     *
     * @return Webhook
     */
    public function setUrl(string $url): Webhook
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret ?? '';
    }

    /**
     * @param string $secret
     *
     * @return Webhook
     */
    public function setSecret(string $secret): Webhook
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version ?? '';
    }

    /**
     * @param string $version
     *
     * @return Webhook
     */
    public function setVersion(string $version): Webhook
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events ?? [];
    }

    /**
     * @param array $events
     *
     * @return Webhook
     */
    public function setEvents(array $events): Webhook
    {
        $this->events = $events;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt ?? '';
    }

    /**
     * @param string $createdAt
     *
     * @return Webhook
     */
    public function setCreatedAt(string $createdAt): Webhook
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getUpdatedAt(): string
    {
        return $this->updatedAt ?? '';
    }

    /**
     * @param string $updatedAt
     *
     * @return Webhook
     */
    public function setUpdatedAt(string $updatedAt): Webhook
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted ?? false;
    }

    /**
     * @param bool $deleted
     *
     * @return Webhook
     */
    public function setDeleted(bool $deleted): Webhook
    {
        $this->deleted = $deleted;
        return $this;
    }
}
