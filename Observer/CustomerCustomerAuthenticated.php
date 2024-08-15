<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerCustomerAuthenticated implements ObserverInterface
{
    public PaymentConsentsInterface $paymentConsents;

    public function __construct(
        PaymentConsentsInterface $paymentConsents
    )
    {
        $this->paymentConsents = $paymentConsents;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        try {
            $customer = $observer->getModel();
            $this->paymentConsents->syncVault($customer->getId());
        } catch (Exception $e) {

        }
    }
}
