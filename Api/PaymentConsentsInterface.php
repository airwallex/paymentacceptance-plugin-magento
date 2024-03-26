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
namespace Airwallex\Payments\Api;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

interface PaymentConsentsInterface
{
    /**
     * @param int $customerId
     * @return string
     */
    public function createAirwallexCustomer($customerId);

    /**
     * @param int $customerId
     * @return SavedPaymentResponseInterface[]
     */
    public function getSavedPayments($customerId);

    /**
     * @param int $customerId
     * @param string $paymentConsentId
     * @return bool
     */
    public function disablePaymentConsent($customerId, $paymentConsentId);
}
