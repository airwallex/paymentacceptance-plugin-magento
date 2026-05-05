<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class PaymentIntent extends AbstractBase
{
    const STATUS_CREATED = 'CREATED';
    const STATUS_REQUIRES_PAYMENT_METHOD = 'REQUIRES_PAYMENT_METHOD';
    const STATUS_REQUIRES_CUSTOMER_ACTION = 'REQUIRES_CUSTOMER_ACTION';
    const STATUS_PENDING = 'PENDING';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_CAPTURE_REQUIRED = 'CAPTURE_REQUIRED';
    const STATUS_REQUIRES_CAPTURE = 'REQUIRES_CAPTURE';
    const STATUS_SUCCEEDED = 'SUCCEEDED';
    const STATUS_CAPTURE_REQUESTED = 'CAPTURE_REQUESTED';
    const STATUS_REQUESTED_CAPTURE = 'REQUESTED_CAPTURE';
    const PAYMENT_METHOD_TYPE_CARD = 'card';
    const THREE_DS_FRICTIONLESS_MAP = [
        'Y' => 'Y - Frictionless transaction',
        'N' => 'N - Non Frictionless transaction',
    ];
    const THREE_DS_AUTHENTICATED_MAP = [
        'Y' => 'Y - Authenticated',
        'N' => 'N - Not Authenticated',
        'U' => 'U - Authentication could not be performed',
        'A' => 'A - Attempted to authenticate',
    ];
    const CURRENCY_TO_DECIMAL = [
        "AED" => 2, "ALL" => 2, "AMD" => 2, "AOA" => 2, "ARS" => 2, "AUD" => 2, "AWG" => 2, "AZN" => 2,
        "BAM" => 2, "BBD" => 2, "BDT" => 2, "BGN" => 2, "BHD" => 3, "BMD" => 2, "BND" => 2, "BOB" => 2,
        "BRL" => 2, "BSD" => 2, "BWP" => 2, "BYN" => 2, "BZD" => 2, "CAD" => 2, "CHF" => 2, "CLP" => 0,
        "CNH" => 2, "CNY" => 2, "COP" => 2, "CRC" => 2, "CUP" => 2, "CVE" => 2, "CZK" => 2, "DJF" => 0,
        "DKK" => 2, "DOP" => 2, "DZD" => 2, "EGP" => 2, "ETB" => 2, "EUR" => 2, "FJD" => 2, "FKP" => 2,
        "GBP" => 2, "GEL" => 2, "GHS" => 2, "GIP" => 2, "GMD" => 2, "GNF" => 0, "GTQ" => 2, "GYD" => 2,
        "HKD" => 2, "HNL" => 2, "HTG" => 2, "HUF" => 0, "IDR" => 0, "ILS" => 2, "INR" => 2, "IQD" => 3,
        "ISK" => 0, "JMD" => 2, "JOD" => 3, "JPY" => 0, "KES" => 2, "KGS" => 2, "KHR" => 2, "KMF" => 0,
        "KRW" => 0, "KWD" => 3, "KYD" => 2, "KZT" => 2, "LAK" => 2, "LBP" => 2, "LKR" => 2, "LYD" => 3,
        "MAD" => 2, "MDL" => 2, "MKD" => 2, "MMK" => 2, "MNT" => 2, "MOP" => 2, "MRU" => 2, "MUR" => 2,
        "MVR" => 2, "MWK" => 2, "MXN" => 2, "MYR" => 2, "MZN" => 2, "NAD" => 2, "NGN" => 2, "NIO" => 2,
        "NOK" => 2, "NPR" => 2, "NZD" => 2, "OMR" => 3, "PAB" => 2, "PEN" => 2, "PGK" => 2, "PHP" => 2,
        "PKR" => 2, "PLN" => 2, "PYG" => 0, "QAR" => 2, "RON" => 2, "RSD" => 2, "RUB" => 2, "RWF" => 0,
        "SAR" => 2, "SBD" => 2, "SCR" => 2, "SEK" => 2, "SGD" => 2, "SHP" => 2, "SLE" => 2, "SOS" => 2,
        "SRD" => 2, "STN" => 2, "SVC" => 2, "SZL" => 2, "THB" => 2, "TND" => 3, "TOP" => 2, "TRY" => 2,
        "TTD" => 2, "TWD" => 0, "TZS" => 2, "UAH" => 2, "UGX" => 0, "USD" => 2, "UYU" => 2, "UZS" => 2,
        "VEF" => 2, "VND" => 0, "VUV" => 0, "WST" => 2, "XAF" => 0, "XCG" => 2, "XCD" => 2, "XOF" => 0,
        "XPF" => 0, "YER" => 2, "ZAR" => 2, "ZMW" => 2, "MGA" => 0, "BIF" => 0
    ];

    /**
     * @var array
     */
    private $additionalInfo;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var float
     */
    private $baseAmount;

    /**
     * @var string
     */
    private $cancellationReason;

    /**
     * @var string
     */
    private $cancelledAt;

    /**
     * @var float
     */
    private $capturedAmount;

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
    private $currency;

    /**
     * @var string
     */
    private $baseCurrency;

    /**
     * @var array
     */
    private $customer;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var string
     */
    private $descriptor;

    /**
     * @var array
     */
    private $fundsSplitData;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $invoiceId;

    /**
     * @var array
     */
    private $latestPaymentAttempt;

    /**
     * @var string
     */
    private $merchantOrderId;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var array
     */
    private $nextAction;

    /**
     * @var array
     */
    private $order;

    /**
     * @var string
     */
    private $paymentConsentId;

    /**
     * @var string
     */
    private $paymentLinkId;

    /**
     * @var array
     */
    private $paymentMethodOptions;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var string
     */
    private $returnUrl;

    /**
     * @var array
     */
    private $riskControlOptions;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $updatedAt;

    /**
     * @var float
     */
    private $refundedAmount;

    /**
     * @return bool
     */
    public function isAuthorized(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUIRES_CAPTURE,
            self::STATUS_CAPTURE_REQUIRED,
        ], true);
    }

    /**
     * @return bool
     */
    public function isCaptured(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCEEDED,
            self::STATUS_CAPTURE_REQUESTED,
            self::STATUS_REQUESTED_CAPTURE,
        ], true);
    }

    /**
     * @return array
     */
    public function getAdditionalInfo(): array
    {
        return $this->additionalInfo ?? [];
    }

    /**
     * @param array $additionalInfo
     *
     * @return PaymentIntent
     */
    public function setAdditionalInfo(array $additionalInfo): PaymentIntent
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     *
     * @return PaymentIntent
     */
    public function setAmount(float $amount): PaymentIntent
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return float
     */
    public function getRefundedAmount(): float
    {
        return $this->refundedAmount ?? 0;
    }

    /**
     * @param float $refundedAmount
     *
     * @return PaymentIntent
     */
    public function setRefundedAmount(float $refundedAmount): PaymentIntent
    {
        $this->refundedAmount = $refundedAmount;
        return $this;
    }

    /**
     * @return float
     */
    public function getBaseAmount(): float
    {
        return $this->baseAmount ?? 0.0;
    }

    /**
     * @param float $baseAmount
     *
     * @return PaymentIntent
     */
    public function setBaseAmount(float $baseAmount): PaymentIntent
    {
        $this->baseAmount = $baseAmount;
        return $this;
    }

    /**
     * @return string
     */
    public function getCancellationReason(): string
    {
        return $this->cancellationReason ?? '';
    }

    /**
     * @param string $cancellationReason
     *
     * @return PaymentIntent
     */
    public function setCancellationReason(string $cancellationReason): PaymentIntent
    {
        $this->cancellationReason = $cancellationReason;
        return $this;
    }

    /**
     * @return string
     */
    public function getCancelledAt(): string
    {
        return $this->cancelledAt ?? '';
    }

    /**
     * @param string $cancelledAt
     *
     * @return PaymentIntent
     */
    public function setCancelledAt(string $cancelledAt): PaymentIntent
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    /**
     * @return float
     */
    public function getCapturedAmount(): float
    {
        return $this->capturedAmount ?? 0.0;
    }

    /**
     * @param float $capturedAmount
     *
     * @return PaymentIntent
     */
    public function setCapturedAmount(float $capturedAmount): PaymentIntent
    {
        $this->capturedAmount = $capturedAmount;
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
     * @return PaymentIntent
     */
    public function setClientSecret(string $clientSecret): PaymentIntent
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
     * @return PaymentIntent
     */
    public function setConnectedAccountId(string $connectedAccountId): PaymentIntent
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
     * @return PaymentIntent
     */
    public function setCreatedAt(string $createdAt): PaymentIntent
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency ?? '';
    }

    /**
     * @param string $currency
     *
     * @return PaymentIntent
     */
    public function setCurrency(string $currency): PaymentIntent
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseCurrency(): string
    {
        return $this->baseCurrency ?? '';
    }

    /**
     * @param string $baseCurrency
     *
     * @return PaymentIntent
     */
    public function setBaseCurrency(string $baseCurrency): PaymentIntent
    {
        $this->baseCurrency = $baseCurrency;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomer(): array
    {
        return $this->customer ?? [];
    }

    /**
     * @param array $customer
     *
     * @return PaymentIntent
     */
    public function setCustomer(array $customer): PaymentIntent
    {
        $this->customer = $customer;
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
     * @return PaymentIntent
     */
    public function setCustomerId(string $customerId): PaymentIntent
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescriptor(): string
    {
        return $this->descriptor ?? '';
    }

    /**
     * @param string $descriptor
     *
     * @return PaymentIntent
     */
    public function setDescriptor(string $descriptor): PaymentIntent
    {
        $this->descriptor = $descriptor;
        return $this;
    }

    /**
     * @return array
     */
    public function getFundsSplitData(): array
    {
        return $this->fundsSplitData ?? [];
    }

    /**
     * @param array $fundsSplitData
     *
     * @return PaymentIntent
     */
    public function setFundsSplitData(array $fundsSplitData): PaymentIntent
    {
        $this->fundsSplitData = $fundsSplitData;
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
     * @return PaymentIntent
     */
    public function setId(string $id): PaymentIntent
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getInvoiceId(): string
    {
        return $this->invoiceId ?? '';
    }

    /**
     * @param string $invoiceId
     *
     * @return PaymentIntent
     */
    public function setInvoiceId(string $invoiceId): PaymentIntent
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }

    /**
     * @return array
     */
    public function getLatestPaymentAttempt(): array
    {
        return $this->latestPaymentAttempt ?? [];
    }

    /**
     * @param array $latestPaymentAttempt
     *
     * @return PaymentIntent
     */
    public function setLatestPaymentAttempt(array $latestPaymentAttempt): PaymentIntent
    {
        $this->latestPaymentAttempt = $latestPaymentAttempt;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantOrderId(): string
    {
        return $this->merchantOrderId ?? '';
    }

    /**
     * @param string $merchantOrderId
     *
     * @return PaymentIntent
     */
    public function setMerchantOrderId(string $merchantOrderId): PaymentIntent
    {
        $this->merchantOrderId = $merchantOrderId;
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
     * @return PaymentIntent
     */
    public function setMetadata(array $metadata): PaymentIntent
    {
        $this->metadata = $metadata;
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
     * @return PaymentIntent
     */
    public function setNextAction(array $nextAction): PaymentIntent
    {
        $this->nextAction = $nextAction;
        return $this;
    }

    /**
     * @return array
     */
    public function getOrder(): array
    {
        return $this->order ?? [];
    }

    /**
     * @param array $order
     *
     * @return PaymentIntent
     */
    public function setOrder(array $order): PaymentIntent
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentConsentId(): string
    {
        return $this->paymentConsentId ?? '';
    }

    /**
     * @param string $paymentConsentId
     *
     * @return PaymentIntent
     */
    public function setPaymentConsentId(string $paymentConsentId): PaymentIntent
    {
        $this->paymentConsentId = $paymentConsentId;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentLinkId(): string
    {
        return $this->paymentLinkId ?? '';
    }

    /**
     * @param string $paymentLinkId
     *
     * @return PaymentIntent
     */
    public function setPaymentLinkId(string $paymentLinkId): PaymentIntent
    {
        $this->paymentLinkId = $paymentLinkId;
        return $this;
    }

    /**
     * @return array
     */
    public function getPaymentMethodOptions(): array
    {
        return $this->paymentMethodOptions ?? [];
    }

    /**
     * @param array $paymentMethodOptions
     *
     * @return PaymentIntent
     */
    public function setPaymentMethodOptions(array $paymentMethodOptions): PaymentIntent
    {
        $this->paymentMethodOptions = $paymentMethodOptions;
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
     * @return PaymentIntent
     */
    public function setRequestId(string $requestId): PaymentIntent
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl ?? '';
    }

    /**
     * @param string $returnUrl
     *
     * @return PaymentIntent
     */
    public function setReturnUrl(string $returnUrl): PaymentIntent
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    /**
     * @return array
     */
    public function getRiskControlOptions(): array
    {
        return $this->riskControlOptions ?? [];
    }

    /**
     * @param array $riskControlOptions
     *
     * @return PaymentIntent
     */
    public function setRiskControlOptions(array $riskControlOptions): PaymentIntent
    {
        $this->riskControlOptions = $riskControlOptions;
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
     * @return PaymentIntent
     */
    public function setStatus(string $status): PaymentIntent
    {
        $this->status = $status;
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
     * @return PaymentIntent
     */
    public function setUpdatedAt(string $updatedAt): PaymentIntent
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return array
     */
    public function getPaymentMethod(): array
    {
        return $this->getLatestPaymentAttempt()['payment_method'] ?? [];
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
