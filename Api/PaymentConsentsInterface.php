<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
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
     * @return ClientSecretResponseInterface
     */
    public function guestGenerateClientSecret(): ClientSecretResponseInterface;

    /**
     * @param ?int $customerId
     * @return string
     */
    public function getAirwallexCustomerIdInDB(?int $customerId): string;
}
