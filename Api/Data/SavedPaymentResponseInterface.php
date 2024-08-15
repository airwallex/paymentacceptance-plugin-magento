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
    public const DATA_KEY_NEXT_TRIGGERED_BY = 'next_triggered_by';
    public const DATA_KEY_NUMBER_TYPE = 'number_type';
    public const DATA_STATUS = 'status';
    public const DATA_BILLING = 'billing';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @param string|null $id
     * @return $this
     */
    public function setId(string $id = null);

    /**
     * @return string|null
     */
    public function getCardBrand();

    /**
     * @param string|null $cardBrand
     * @return $this
     */
    public function setCardBrand(string $cardBrand = null);

    /**
     * @return string|null
     */
    public function getCardExpiryMonth();

    /**
     * @param string|null $expiryMonth
     * @return $this
     */
    public function setCardExpiryMonth(string $expiryMonth = null);

    /**
     * @return string|null
     */
    public function getCardExpiryYear();

    /**
     * @param string|null $expiryYear
     * @return $this
     */
    public function setCardExpiryYear(string $expiryYear = null);

    /**
     * @return string|null
     */
    public function getCardLastFour();

    /**
     * @param string|null $lastFour
     * @return $this
     */
    public function setCardLastFour(string $lastFour = null);

    /**
     * @return string|null
     */
    public function getCardHolderName();

    /**
     * @param string|null $holderName
     * @return $this
     */
    public function setCardHolderName(string $holderName = null);

    /**
     * @param string|null $icon
     * @return $this
     */
    public function setCardIcon(string $icon = null);

    /**
     * @return string|null
     */
    public function getCardIcon();

    /**
     * @return string|null
     */
    public function getNextTriggeredBy();

    /**
     * @param string|null $nextTriggeredBy
     * @return $this
     */
    public function setNextTriggeredBy(string $nextTriggeredBy = null);

    /**
     * @return string|null
     */
    public function getNumberType();

    /**
     * @param string|null $numberType
     * @return $this
     */
    public function setNumberType(string $numberType = null);

    /**
     * @return string|null
     */
    public function getStatus();

    /**
     * @param string|null $status
     * @return $this
     */
    public function setStatus(string $status = null);

    /**
     * @return string|null
     */
    public function getBilling();

    /**
     * @param string|null $billing
     * @return $this
     */
    public function setBilling(string $billing = null);
}
