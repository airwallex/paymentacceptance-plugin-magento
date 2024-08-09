<?php

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
    public function createAirwallexCustomerById(int $customerId): string;

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function generateAirwallexCustomerId(CustomerInterface $customer): string;

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function createAirwallexCustomer(CustomerInterface $customer): string;

    /**
     * @param int $customerId
     * @return SavedPaymentResponseInterface[]
     */
    public function getSavedCards(int $customerId): array;

    /**
     * @param int $customerId
     * @param string $paymentConsentId
     * @return bool
     */
    public function disablePaymentConsent(int $customerId, string $paymentConsentId): bool;

    /**
     * @param int $customerId
     * @return bool
     */
    public function syncVault(int $customerId): bool;

    /**
     * @param int $customerId
     * @return ClientSecretResponseInterface
     */
    public function generateClientSecret(int $customerId): ClientSecretResponseInterface;

    /**
     * @param ?int $customerId
     * @return string
     */
    public function getAirwallexCustomerIdInDB(?int $customerId): string;
}
