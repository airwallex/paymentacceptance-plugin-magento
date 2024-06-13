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

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Airwallex\Payments\Api\Data\ClientSecretResponseInterface;

interface PaymentConsentsInterface
{
    /**
     * @param int $customerId
     * @return string
     */
    public function createAirwallexCustomerById($customerId);

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function generateAirwallexCustomerId($customer);

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function createAirwallexCustomer($customer);

    /**
     * @param int $customerId
     * @return SavedPaymentResponseInterface[]
     */
    public function getSavedCards($customerId);

    /**
     * @param int $customerId
     * @param string $paymentConsentId
     * @return bool
     */
    public function disablePaymentConsent($customerId, $paymentConsentId);

    /**
     * @param int $customerId
     * @return bool
     */
    public function syncVault($customerId);

    /**
     * @param int $customerId
     * @return ClientSecretResponseInterface
     */
    public function generateClientSecret($customerId);

    /**
     * @param int $customerId
     * @return string
     */
    public function getAirwallexCustomerIdInDB($customerId);
}
