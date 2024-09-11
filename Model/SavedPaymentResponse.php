<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Magento\Framework\DataObject;

class SavedPaymentResponse extends DataObject implements SavedPaymentResponseInterface
{
    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->getData(self::DATA_KEY_ID);
    }

    /**
     * @param string|null $id
     * @return SavedPaymentResponse
     */
    public function setId(string $id = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_ID, $id);
    }

    /**
     * @return string|null
     */
    public function getCardBrand(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_BRAND);
    }

    /**
     * @param string|null $cardBrand
     * @return SavedPaymentResponse
     */
    public function setCardBrand(string $cardBrand = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_BRAND, $cardBrand);
    }

    /**
     * @return string|null
     */
    public function getCardExpiryMonth(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_EXPIRY_MONTH);
    }

    /**
     * @param string|null $expiryMonth
     * @return SavedPaymentResponse
     */
    public function setCardExpiryMonth(string $expiryMonth = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_EXPIRY_MONTH, $expiryMonth);
    }

    /**
     * @return string|null
     */
    public function getCardExpiryYear(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_EXPIRY_YEAR);
    }

    /**
     * @param string|null $expiryYear
     * @return SavedPaymentResponse
     */
    public function setCardExpiryYear(string $expiryYear = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_EXPIRY_YEAR, $expiryYear);
    }

    /**
     * @return string|null
     */
    public function getCardLastFour(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_LAST_FOUR);
    }

    /**
     * @param string|null $lastFour
     * @return SavedPaymentResponse
     */
    public function setCardLastFour(string $lastFour = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_LAST_FOUR, $lastFour);
    }

    /**
     * @return string|null
     */
    public function getCardHolderName(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_HOLDER_NAME);
    }

    /**
     * @param string|null $holderName
     * @return SavedPaymentResponse
     */
    public function setCardHolderName(string $holderName = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_HOLDER_NAME, $holderName);
    }

    /**
     * @return string|null
     */
    public function getCardIcon(): ?string
    {
        return $this->getData(self::DATA_KEY_CARD_ICON);
    }

    /**
     * @return string|null
     */
    public function getPaymentMethodId(): ?string
    {
        return $this->getData(self::DATA_KEY_PAYMENT_METHOD_ID);
    }

    /**
     * @param string|null $icon
     * @return SavedPaymentResponse
     */
    public function setCardIcon(string $icon = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_CARD_ICON, $icon);
    }

    /**
     * @return string|null
     */
    public function getNextTriggeredBy(): ?string
    {
        return $this->getData(self::DATA_KEY_NEXT_TRIGGERED_BY);
    }

    /**
     * @param string|null $nextTriggeredBy
     * @return $this
     */
    public function setNextTriggeredBy(string $nextTriggeredBy = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_NEXT_TRIGGERED_BY, $nextTriggeredBy);
    }

    /**
     * @return string|null
     */
    public function getNumberType(): ?string
    {
        return $this->getData(self::DATA_KEY_NUMBER_TYPE);
    }

    /**
     * @param string|null $numberType
     * @return $this
     */
    public function setNumberType(string $numberType = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_KEY_NUMBER_TYPE, $numberType);
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->getData(self::DATA_STATUS);
    }

    /**
     * @param string|null $status
     * @return $this
     */
    public function setStatus(string $status = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_STATUS, $status);
    }

    /**
     * @return string|null
     */
    public function getBilling(): ?string
    {
        return $this->getData(self::DATA_BILLING);
    }

    /**
     * @param string|null $billing
     * @return $this
     */
    public function setBilling(string $billing = null): SavedPaymentResponse
    {
        return $this->setData(self::DATA_BILLING, $billing);
    }
}
