<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
}
