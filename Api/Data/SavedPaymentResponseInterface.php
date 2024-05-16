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
}
