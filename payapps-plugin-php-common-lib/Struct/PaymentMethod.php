<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class PaymentMethod extends AbstractBase
{
    const STATUS_CREATED = 'CREATED';
    const STATUS_VERIFIED = 'VERIFIED';
    const STATUS_DISABLED = 'DISABLED';

    /**
     * @var array
     */
    private $achDirectDebit;

    /**
     * @var array
     */
    private $applepay;

    /**
     * @var array
     */
    private $bacsDirectDebit;

    /**
     * @var array
     */
    private $becsDirectDebit;

    /**
     * @var array
     */
    private $card;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var array
     */
    private $eftDirectDebit;

    /**
     * @var array
     */
    private $googlepay;

    /**
     * @var string
     */
    private $id;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var array
     */
    private $sepaDirectDebit;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @return bool
     */
    public function isCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    /**
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * @return array
     */
    public function getAchDirectDebit(): array
    {
        return $this->achDirectDebit ?? [];
    }

    /**
     * @param array $achDirectDebit
     *
     * @return PaymentMethod
     */
    public function setAchDirectDebit(array $achDirectDebit): PaymentMethod
    {
        $this->achDirectDebit = $achDirectDebit;
        return $this;
    }

    /**
     * @return array
     */
    public function getApplepay(): array
    {
        return $this->applepay ?? [];
    }

    /**
     * @param array $applepay
     *
     * @return PaymentMethod
     */
    public function setApplepay(array $applepay): PaymentMethod
    {
        $this->applepay = $applepay;
        return $this;
    }

    /**
     * @return array
     */
    public function getBacsDirectDebit(): array
    {
        return $this->bacsDirectDebit ?? [];
    }

    /**
     * @param array $bacsDirectDebit
     *
     * @return PaymentMethod
     */
    public function setBacsDirectDebit(array $bacsDirectDebit): PaymentMethod
    {
        $this->bacsDirectDebit = $bacsDirectDebit;
        return $this;
    }

    /**
     * @return array
     */
    public function getBecsDirectDebit(): array
    {
        return $this->becsDirectDebit ?? [];
    }

    /**
     * @param array $becsDirectDebit
     *
     * @return PaymentMethod
     */
    public function setBecsDirectDebit(array $becsDirectDebit): PaymentMethod
    {
        $this->becsDirectDebit = $becsDirectDebit;
        return $this;
    }

    /**
     * @return array
     */
    public function getCard(): array
    {
        return $this->card ?? [];
    }

    /**
     * @param array $card
     *
     * @return PaymentMethod
     */
    public function setCard(array $card): PaymentMethod
    {
        $this->card = $card;
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
     * @return PaymentMethod
     */
    public function setCreatedAt(string $createdAt): PaymentMethod
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->customerId ?? '';
    }

    /**
     * @param string $customerId
     *
     * @return PaymentMethod
     */
    public function setCustomerId(string $customerId): PaymentMethod
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @return array
     */
    public function getEftDirectDebit(): array
    {
        return $this->eftDirectDebit ?? [];
    }

    /**
     * @param array $eftDirectDebit
     *
     * @return PaymentMethod
     */
    public function setEftDirectDebit(array $eftDirectDebit): PaymentMethod
    {
        $this->eftDirectDebit = $eftDirectDebit;
        return $this;
    }

    /**
     * @return array
     */
    public function getGooglepay(): array
    {
        return $this->googlepay ?? [];
    }

    /**
     * @param array $googlepay
     *
     * @return PaymentMethod
     */
    public function setGooglepay(array $googlepay): PaymentMethod
    {
        $this->googlepay = $googlepay;
        return $this;
    }

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
     * @return PaymentMethod
     */
    public function setId(string $id): PaymentMethod
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * @param array $metadata
     *
     * @return PaymentMethod
     */
    public function setMetadata(array $metadata): PaymentMethod
    {
        $this->metadata = $metadata;
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
     * @return PaymentMethod
     */
    public function setRequestId(string $requestId): PaymentMethod
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return array
     */
    public function getSepaDirectDebit(): array
    {
        return $this->sepaDirectDebit ?? [];
    }

    /**
     * @param array $sepaDirectDebit
     *
     * @return PaymentMethod
     */
    public function setSepaDirectDebit(array $sepaDirectDebit): PaymentMethod
    {
        $this->sepaDirectDebit = $sepaDirectDebit;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status ?? '';
    }

    /**
     * @param string $status
     *
     * @return PaymentMethod
     */
    public function setStatus(string $status): PaymentMethod
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type ?? '';
    }

    /**
     * @param string $type
     *
     * @return PaymentMethod
     */
    public function setType(string $type): PaymentMethod
    {
        $this->type = $type;
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
     * @return PaymentMethod
     */
    public function setUpdatedAt(string $updatedAt): PaymentMethod
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
