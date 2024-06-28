<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Magento\Framework\DataObject;

class SavedPaymentResponse extends DataObject implements SavedPaymentResponseInterface
{
    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->getData(self::DATA_KEY_ID);
    }

    /**
     * @param string|null $id
     * @return SavedPaymentResponse
     */
    public function setId(string $id = null)
    {
        return $this->setData(self::DATA_KEY_ID, $id);
    }

    /**
     * @return string|null
     */
    public function getCardBrand()
    {
        return $this->getData(self::DATA_KEY_CARD_BRAND);
    }

    /**
     * @param string|null $cardBrand
     * @return SavedPaymentResponse
     */
    public function setCardBrand(string $cardBrand = null)
    {
        return $this->setData(self::DATA_KEY_CARD_BRAND, $cardBrand);
    }

    /**
     * @return string|null
     */
    public function getCardExpiryMonth()
    {
        return $this->getData(self::DATA_KEY_CARD_EXPIRY_MONTH);
    }

    /**
     * @param string|null $expiryMonth
     * @return SavedPaymentResponse
     */
    public function setCardExpiryMonth(string $expiryMonth = null)
    {
        return $this->setData(self::DATA_KEY_CARD_EXPIRY_MONTH, $expiryMonth);
    }

    /**
     * @return string|null
     */
    public function getCardExpiryYear()
    {
        return $this->getData(self::DATA_KEY_CARD_EXPIRY_YEAR);
    }

    /**
     * @param string|null $expiryYear
     * @return SavedPaymentResponse
     */
    public function setCardExpiryYear(string $expiryYear = null)
    {
        return $this->setData(self::DATA_KEY_CARD_EXPIRY_YEAR, $expiryYear);
    }

    /**
     * @return string|null
     */
    public function getCardLastFour()
    {
        return $this->getData(self::DATA_KEY_CARD_LAST_FOUR);
    }

    /**
     * @param string|null $lastFour
     * @return SavedPaymentResponse
     */
    public function setCardLastFour(string $lastFour = null)
    {
        return $this->setData(self::DATA_KEY_CARD_LAST_FOUR, $lastFour);
    }

    /**
     * @return string|null
     */
    public function getCardHolderName()
    {
        return $this->getData(self::DATA_KEY_CARD_HOLDER_NAME);
    }

    /**
     * @param string|null $holderName
     * @return SavedPaymentResponse
     */
    public function setCardHolderName(string $holderName = null)
    {
        return $this->setData(self::DATA_KEY_CARD_HOLDER_NAME, $holderName);
    }

    /**
     * @return string|null
     */
    public function getCardIcon()
    {
        return $this->getData(self::DATA_KEY_CARD_ICON);
    }

    /**
     * @param string|null $icon
     * @return SavedPaymentResponse
     */
    public function setCardIcon(string $icon = null)
    {
        return $this->setData(self::DATA_KEY_CARD_ICON, $icon);
    }

    /**
     * @return string|null
     */
    public function getNextTriggeredBy()
    {
        return $this->getData(self::DATA_KEY_NEXT_TRIGGERED_BY);
    }

    /**
     * @param string|null $nextTriggeredBy
     * @return $this
     */
    public function setNextTriggeredBy(string $nextTriggeredBy = null)
    {
        return $this->setData(self::DATA_KEY_NEXT_TRIGGERED_BY, $nextTriggeredBy);
    }

    /**
     * @return string|null
     */
    public function getNumberType()
    {
        return $this->getData(self::DATA_KEY_NUMBER_TYPE);
    }

    /**
     * @param string|null $numberType
     * @return $this
     */
    public function setNumberType(string $numberType = null)
    {
        return $this->setData(self::DATA_KEY_NUMBER_TYPE, $numberType);
    }

    /**
     * @return string|null
     */
    public function getBilling()
    {
        return $this->getData(self::DATA_BILLING);
    }

    /**
     * @param string|null $billing
     * @return $this
     */
    public function setBilling(string $billing = null)
    {
        return $this->setData(self::DATA_BILLING, $billing);
    }
}
