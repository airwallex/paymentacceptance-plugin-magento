<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Customer extends AbstractBase
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
    private $businessName;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var array
     */
    private $address;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $firstName;

    /**
     * @var string
     */
    private $lastName;

    /**
     * @var string
     */
    private $merchantCustomerId;

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
    private $phoneNumber;

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
     * @return Customer
     */
    public function setId(string $id): Customer
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
     * @return Customer
     */
    public function setRequestId(string $requestId): Customer
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getBusinessName(): string
    {
        return $this->businessName ?? '';
    }

    /**
     * @param string $businessName
     *
     * @return Customer
     */
    public function setBusinessName(string $businessName): Customer
    {
        $this->businessName = $businessName;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret ?? '';
    }

    /**
     * @param string $clientSecret
     *
     * @return Customer
     */
    public function setClientSecret(string $clientSecret): Customer
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @return array
     */
    public function getAddress(): array
    {
        return $this->address ?? [];
    }

    /**
     * @param array $address
     *
     * @return Customer
     */
    public function setAddress(array $address): Customer
    {
        $this->address = $address;
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
     * @return Customer
     */
    public function setMetadata(array $metadata): Customer
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email ?? '';
    }

    /**
     * @param string $email
     *
     * @return Customer
     */
    public function setEmail(string $email): Customer
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName ?? '';
    }

    /**
     * @param string $firstName
     *
     * @return Customer
     */
    public function setFirstName(string $firstName): Customer
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName ?? '';
    }

    /**
     * @param string $lastName
     *
     * @return Customer
     */
    public function setLastName(string $lastName): Customer
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantCustomerId(): string
    {
        return $this->merchantCustomerId ?? '';
    }

    /**
     * @param string $merchantCustomerId
     *
     * @return Customer
     */
    public function setMerchantCustomerId(string $merchantCustomerId): Customer
    {
        $this->merchantCustomerId = $merchantCustomerId;
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
     * @return Customer
     */
    public function setCreatedAt(string $createdAt): Customer
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
     * @return Customer
     */
    public function setUpdatedAt(string $updatedAt): Customer
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getPhoneNumber(): string
    {
        return $this->phoneNumber ?? '';
    }

    /**
     * @param string $phoneNumber
     *
     * @return Customer
     */
    public function setPhoneNumber(string $phoneNumber): Customer
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }
}
