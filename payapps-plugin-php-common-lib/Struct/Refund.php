<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Refund extends AbstractBase
{
    const STATUS_RECEIVED = 'RECEIVED';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_SETTLED = 'SETTLED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_SUCCEEDED = 'SUCCEEDED';
    const STATUS_PROCESSING = 'PROCESSING';

    /**
     * @var string
     */
    private $id;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var string
     */
    private $paymentAttemptId;

    /**
     * @var string
     */
    private $paymentIntentId;

    /**
     * @var string
     */
    private $reason;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $acquirerReferenceNumber;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var array
     */
    private $metadata = [];

    // Getters and Setters

    public function getId(): string
    {
        return $this->id ?? '';
    }

    public function setId(string $id): Refund
    {
        $this->id = $id;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount ?? 0.0;
    }

    public function setAmount(float $amount): Refund
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency ?? '';
    }

    public function setCurrency(string $currency): Refund
    {
        $this->currency = $currency;
        return $this;
    }

    public function getPaymentAttemptId(): string
    {
        return $this->paymentAttemptId ?? '';
    }

    public function setPaymentAttemptId(string $paymentAttemptId): Refund
    {
        $this->paymentAttemptId = $paymentAttemptId;
        return $this;
    }

    public function getPaymentIntentId(): string
    {
        return $this->paymentIntentId ?? '';
    }

    public function setPaymentIntentId(string $paymentIntentId): Refund
    {
        $this->paymentIntentId = $paymentIntentId;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason ?? '';
    }

    public function setReason(string $reason): Refund
    {
        $this->reason = $reason;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status ?? '';
    }

    public function setStatus(string $status): Refund
    {
        $this->status = $status;
        return $this;
    }

    public function getAcquirerReferenceNumber(): string
    {
        return $this->acquirerReferenceNumber ?? '';
    }

    public function setAcquirerReferenceNumber(string $acquirerReferenceNumber): Refund
    {
        $this->acquirerReferenceNumber = $acquirerReferenceNumber;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt ?? '';
    }

    public function setCreatedAt(string $createdAt): Refund
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt ?? '';
    }

    public function setUpdatedAt(string $updatedAt): Refund
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRequestId(): string
    {
        return $this->requestId ?? '';
    }

    public function setRequestId(string $requestId): Refund
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(array $metadata): Refund
    {
        $this->metadata = $metadata;
        return $this;
    }
}
