<?php

namespace Airwallex\Payments\Observer;

use Magento\Quote\Model\Quote;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class QuoteSubmitSuccess implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $method = $order->getPayment()->getMethod();
        if (strpos($method, 'airwallex') !== 0) return;

        /** @var Quote $quote */
        $quote = $observer->getQuote();
        $quote->setIsActive(true);
    }
}
