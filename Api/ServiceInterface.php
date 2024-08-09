<?php

namespace Airwallex\Payments\Api;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Exception;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

interface ServiceInterface
{
    /**
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string $from
     * @return PlaceOrderResponseInterface
     */
    public function airwallexGuestPlaceOrder(
        string           $cartId,
        string           $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface;

    /**
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string $from
     * @return PlaceOrderResponseInterface
     */
    public function airwallexPlaceOrder(
        string           $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface;

    /**
     * @return string
     */
    public function redirectUrl(): string;

    /**
     * Get express data when initialize and quote data updated
     *
     * @return string
     */
    public function expressData(): string;

    /**
     * Get intent
     *
     * @param string $intentId
     * @return string
     */
    public function intent(string $intentId): string;

    /**
     * Add to cart
     *
     * @return string
     */
    public function addToCart(): string;

    /**
     * Post Address to get method and quote data
     *
     * @return string
     */
    public function postAddress(): string;

    /**
     * Get region id
     *
     * @param string $country
     * @param string $region
     * @return string
     */
    public function regionId(string $country, string $region): string;

    /**
     * Apple pay validate merchant
     *
     * @return string
     * @throws Exception
     */
    public function validateMerchant(): string;

    /**
     * Validate addresses before placing order
     *
     * @return string
     * @throws Exception
     */
    public function validateAddresses(): string;
}
