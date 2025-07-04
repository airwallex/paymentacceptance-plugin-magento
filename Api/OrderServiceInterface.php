<?php

namespace Airwallex\Payments\Api;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

interface OrderServiceInterface
{
    const ORDER_BEFORE_PAYMENT = 'order_before_payment';
    const PAYMENT_BEFORE_ORDER = 'payment_before_order';

    /**
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $paymentMethodId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     */
    public function airwallexGuestPlaceOrder(
        string           $cartId,
        string           $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface;

    /**
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $paymentMethodId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     */
    public function airwallexPlaceOrder(
        string           $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface;
}
