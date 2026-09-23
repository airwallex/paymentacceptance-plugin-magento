<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class PaymentConsent extends AbstractBase
{
    /**
     * @var string
     */
    const STATUS_VERIFIED = 'VERIFIED';

    /**
     * @var string
     */
    const TRIGGERED_BY_CUSTOMER = 'customer';

    /**
     * @var string
     */
    const TRIGGERED_BY_MERCHANT = 'merchant';

    /**
     * @var string
     */
    const MERCHANT_TRIGGER_REASON_SCHEDULED = 'scheduled';

    /**
     * @var string
     */
    const MERCHANT_TRIGGER_REASON_UNSCHEDULED = 'unscheduled';

    /**
     * @var string
     */
    const MERCHANT_TRIGGER_REASON_INSTALLMENTS = 'installments';

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $connectedAccountId;

    /**
     * @var string
     */
    private $createdAt;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var string
     */
    private $disableReason = '';

    /**
     * @var array
     */
    private $mandate = [];

    /**
     * @var array
     */
    private $metadata = [];

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $initialPaymentIntentId;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @var array
     */
    private $nextAction;

    /**
     * @var array
     */
    private $failureReason = [];

    /**
     * @var string
     */
    private $merchantTriggerReason;

    /**
     * @var string
     */
    private $nextTriggeredBy;

    /**
     * @var array
     */
    private $paymentMethod = [];

    /**
     * @var string
     */
    private $requestId;

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
     * @return PaymentConsent
     */
    public function setId(string $id): PaymentConsent
    {
        $this->id = $id;
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
     * @return PaymentConsent
     */
    public function setClientSecret(string $clientSecret): PaymentConsent
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @return string
     */
    public function getConnectedAccountId(): string
    {
        return $this->connectedAccountId ?? '';
    }

    /**
     * @param string $connectedAccountId
     *
     * @return PaymentConsent
     */
    public function setConnectedAccountId(string $connectedAccountId): PaymentConsent
    {
        $this->connectedAccountId = $connectedAccountId;
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
     * @return PaymentConsent
     */
    public function setCreatedAt(string $createdAt): PaymentConsent
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
     * @return PaymentConsent
     */
    public function setCustomerId(string $customerId): PaymentConsent
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @return string
     */
    public function getDisableReason(): string
    {
        return $this->disableReason ?? '';
    }

    /**
     * @param string $disableReason
     *
     * @return PaymentConsent
     */
    public function setDisableReason(string $disableReason): PaymentConsent
    {
        $this->disableReason = $disableReason;
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
     * @return PaymentConsent
     */
    public function setMetadata(array $metadata): PaymentConsent
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return array
     */
    public function getMandate(): array
    {
        return $this->mandate ?? [];
    }

    /**
     * @param array $mandate
     *
     * @return PaymentConsent
     */
    public function setMandate(array $mandate): PaymentConsent
    {
        $this->mandate = $mandate;
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
     * @return PaymentConsent
     */
    public function setStatus(string $status): PaymentConsent
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getInitialPaymentIntentId(): string
    {
        return $this->initialPaymentIntentId ?? '';
    }

    /**
     * @param string $initialPaymentIntentId
     *
     * @return PaymentConsent
     */
    public function setInitialPaymentIntentId(string $initialPaymentIntentId): PaymentConsent
    {
        $this->initialPaymentIntentId = $initialPaymentIntentId;
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
     * @return PaymentConsent
     */
    public function setUpdatedAt(string $updatedAt): PaymentConsent
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return array
     */
    public function getNextAction(): array
    {
        return $this->nextAction ?? [];
    }

    /**
     * @param array $nextAction
     *
     * @return PaymentConsent
     */
    public function setNextAction(array $nextAction): PaymentConsent
    {
        $this->nextAction = $nextAction;
        return $this;
    }

    /**
     * @return array
     */
    public function getFailureReason(): array
    {
        return $this->failureReason ?? [];
    }

    /**
     * @param array $failureReason
     *
     * @return PaymentConsent
     */
    public function setFailureReason(array $failureReason): PaymentConsent
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantTriggerReason(): string
    {
        return $this->merchantTriggerReason ?? '';
    }

    /**
     * @param string $merchantTriggerReason
     *
     * @return PaymentConsent
     */
    public function setMerchantTriggerReason(string $merchantTriggerReason): PaymentConsent
    {
        $this->merchantTriggerReason = $merchantTriggerReason;
        return $this;
    }

    /**
     * @return string
     */
    public function getNextTriggeredBy(): string
    {
        return $this->nextTriggeredBy ?? '';
    }

    /**
     * @param string $nextTriggeredBy
     *
     * @return PaymentConsent
     */
    public function setNextTriggeredBy(string $nextTriggeredBy): PaymentConsent
    {
        $this->nextTriggeredBy = $nextTriggeredBy;
        return $this;
    }

    /**
     * @return array
     */
    public function getPaymentMethod(): array
    {
        return $this->paymentMethod ?? [];
    }

    /**
     * @param array $paymentMethod
     *
     * @return PaymentConsent
     */
    public function setPaymentMethod(array $paymentMethod): PaymentConsent
    {
        $this->paymentMethod = $paymentMethod;
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
     * @return PaymentConsent
     */
    public function setRequestId(string $requestId): PaymentConsent
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethodType(): string
    {
        return $this->getPaymentMethod()['type'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardLast4(): string
    {
        return $this->getPaymentMethod()['card']['last4'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardNumberType(): string
    {
        return $this->getPaymentMethod()['card']['number_type'] ?? '';
    }

    /**
     * @return array
     */
    public function getCardBilling(): array
    {
        return $this->getPaymentMethod()['card']['billing'] ?? [];
    }

    /**
     * @return string
     */
    public function getCardBrand(): string
    {
        return $this->getPaymentMethod()['card']['brand'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardType(): string
    {
        return $this->getPaymentMethod()['card']['card_type'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardIssuerName(): string
    {
        return $this->getPaymentMethod()['card']['issuer_name'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardIssuerCountryCode(): string
    {
        return $this->getPaymentMethod()['card']['issuer_country_code'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardBin(): string
    {
        return $this->getPaymentMethod()['card']['bin'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardFingerprint(): string
    {
        return $this->getPaymentMethod()['card']['fingerprint'] ?? '';
    }

    /**
     * @return bool
     */
    public function isCommercialCard(): bool
    {
        return $this->getPaymentMethod()['card']['is_commercial'] ?? false;
    }

    /**
     * @return string
     */
    public function getCardExpiryMonth(): string
    {
        return $this->getPaymentMethod()['card']['expiry_month'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardExpiryYear(): string
    {
        return $this->getPaymentMethod()['card']['expiry_year'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardHolderName(): string
    {
        return $this->getPaymentMethod()['card']['name'] ?? '';
    }

    /**
     * @return string
     */
    public function getCardLifecycleId(): string
    {
        return $this->getPaymentMethod()['card']['lifecycle_id'] ?? '';
    }

    /**
     * @return array
     */
    public function getKoreanCardInfo(): array
    {
        return $this->getPaymentMethod()['card']['korean_card'] ?? [];
    }

    /**
     * @return array
     */
    public function getCardAdditionalInfo(): array
    {
        return $this->getPaymentMethod()['card']['additional_info'] ?? [];
    }

    /**
     * @return string
     */
    public function getBillingCity(): string
    {
        return $this->getCardBilling()['address']['city'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingCountryCode(): string
    {
        return $this->getCardBilling()['address']['country_code'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingPostcode(): string
    {
        return $this->getCardBilling()['address']['postcode'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingState(): string
    {
        return $this->getCardBilling()['address']['state'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingStreet(): string
    {
        return $this->getCardBilling()['address']['street'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingFirstName(): string
    {
        return $this->getCardBilling()['first_name'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingLastName(): string
    {
        return $this->getCardBilling()['last_name'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingEmail(): string
    {
        return $this->getCardBilling()['email'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingPhoneNumber(): string
    {
        return $this->getCardBilling()['phone_number'] ?? '';
    }

    /**
     * @return string
     */
    public function getBillingDateOfBirth(): string
    {
        return $this->getCardBilling()['date_of_birth'] ?? '';
    }
}
