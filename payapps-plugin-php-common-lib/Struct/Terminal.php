<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Terminal extends AbstractBase
{
    // Admin password statuses
    const ADMIN_PASSWORD_ACTIVE = 'ACTIVE';
    const ADMIN_PASSWORD_LOCKED = 'LOCKED';
    const ADMIN_PASSWORD_RESET_REQUESTED = 'RESET_REQUESTED';

    // Refund password statuses
    const REFUND_PASSWORD_ACTIVE = 'ACTIVE';
    const REFUND_PASSWORD_LOCKED = 'LOCKED';
    const REFUND_PASSWORD_RESET_REQUESTED = 'RESET_REQUESTED';
    const REFUND_PASSWORD_OPT_OUT = 'OPT_OUT';

    // Terminal statuses
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_INACTIVE = 'INACTIVE';
    const STATUS_TERMINATED = 'TERMINATED';

    /**
     * @var string
     */
    private $adminPasswordStatus;

    /**
     * @var string
     */
    private $connectedAccountId;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $model;

    /**
     * @var string
     */
    private $nickname;

    /**
     * @var string
     */
    private $refundPasswordStatus;

    /**
     * @var string
     */
    private $serialNumber;

    /**
     * @var string
     */
    private $status;

    // === Getters and Setters ===

    public function getAdminPasswordStatus(): string
    {
        return $this->adminPasswordStatus ?? '';
    }

    public function setAdminPasswordStatus(string $status): Terminal
    {
        $this->adminPasswordStatus = $status;
        return $this;
    }

    public function getConnectedAccountId(): string
    {
        return $this->connectedAccountId ?? '';
    }

    public function setConnectedAccountId(string $connectedAccountId): Terminal
    {
        $this->connectedAccountId = $connectedAccountId;
        return $this;
    }

    public function getId(): string
    {
        return $this->id ?? '';
    }

    public function setId(string $id): Terminal
    {
        $this->id = $id;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model ?? '';
    }

    public function setModel(string $model): Terminal
    {
        $this->model = $model;
        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname ?? '';
    }

    public function setNickname(string $nickname): Terminal
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getRefundPasswordStatus(): string
    {
        return $this->refundPasswordStatus ?? '';
    }

    public function setRefundPasswordStatus(string $status): Terminal
    {
        $this->refundPasswordStatus = $status;
        return $this;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber ?? '';
    }

    public function setSerialNumber(string $serialNumber): Terminal
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status ?? '';
    }

    public function setStatus(string $status): Terminal
    {
        $this->status = $status;
        return $this;
    }
}
