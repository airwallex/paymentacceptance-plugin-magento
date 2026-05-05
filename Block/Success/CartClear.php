<?php

namespace Airwallex\Payments\Block\Success;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class CartClear extends Template
{
    protected CheckoutSession $checkoutSession;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
    }

    public function isAirwallexPayment(): bool
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            if (!$order || !$order->getId()) {
                return false;
            }

            $payment = $order->getPayment();
            if (!$payment) {
                return false;
            }

            $paymentMethod = $payment->getMethod();
            if (empty($paymentMethod)) {
                return false;
            }

            return strpos($paymentMethod, 'airwallex_payments') === 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
