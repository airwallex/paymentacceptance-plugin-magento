<?php

namespace Airwallex\Payments\Api\Data;

interface SavedPaymentResponseInterface
{
    public const DATA_KEY_ID = 'id';
    public const DATA_KEY_CARD_BRAND = 'card_brand';
    public const DATA_KEY_CARD_EXPIRY_MONTH = 'card_expiry_month';
    public const DATA_KEY_CARD_EXPIRY_YEAR = 'card_expiry_year';
    public const DATA_KEY_CARD_LAST_FOUR = 'card_last_four';
    public const DATA_KEY_CARD_HOLDER_NAME = 'card_holder_name';
    public const DATA_KEY_CARD_ICON = 'card_icon';
    public const DATA_KEY_PAYMENT_METHOD_ID = 'payment_method_id';
    public const DATA_KEY_NEXT_TRIGGERED_BY = 'next_triggered_by';
    public const DATA_KEY_NUMBER_TYPE = 'number_type';
    public const DATA_STATUS = 'status';
    public const DATA_BILLING = 'billing';

    /**
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * @param string|null $id
     * @return $this
     */
    public function setId(string $id = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardBrand(): ?string;

    /**
     * @param string|null $cardBrand
     * @return $this
     */
    public function setCardBrand(string $cardBrand = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardExpiryMonth(): ?string;

    /**
     * @param string|null $expiryMonth
     * @return $this
     */
    public function setCardExpiryMonth(string $expiryMonth = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardExpiryYear(): ?string;

    /**
     * @param string|null $expiryYear
     * @return $this
     */
    public function setCardExpiryYear(string $expiryYear = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardLastFour(): ?string;

    /**
     * @param string|null $lastFour
     * @return $this
     */
    public function setCardLastFour(string $lastFour = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardHolderName(): ?string;

    /**
     * @param string|null $holderName
     * @return $this
     */
    public function setCardHolderName(string $holderName = null): SavedPaymentResponseInterface;

    /**
     * @param string|null $icon
     * @return $this
     */
    public function setCardIcon(string $icon = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getCardIcon(): ?string;

    /**
     * @return string|null
     */
    public function getPaymentMethodId(): ?string;

    /**
     * @return string|null
     */
    public function getNextTriggeredBy(): ?string;

    /**
     * @param string|null $nextTriggeredBy
     * @return $this
     */
    public function setNextTriggeredBy(string $nextTriggeredBy = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getNumberType(): ?string;

    /**
     * @param string|null $numberType
     * @return $this
     */
    public function setNumberType(string $numberType = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * @param string|null $status
     * @return $this
     */
    public function setStatus(string $status = null): SavedPaymentResponseInterface;

    /**
     * @return string|null
     */
    public function getBilling(): ?string;

    /**
     * @param string|null $billing
     * @return $this
     */
    public function setBilling(string $billing = null): SavedPaymentResponseInterface;
}
