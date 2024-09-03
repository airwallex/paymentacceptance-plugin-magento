<?php

namespace Airwallex\Payments\Observer;

use Magento\Quote\Model\Quote;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;

class QuoteSubmitSuccess implements ObserverInterface
{
    public IsOrderCreatedHelper $isOrderCreatedHelper;

    public function __construct(
        IsOrderCreatedHelper $isOrderCreatedHelper
    ) {
        $this->isOrderCreatedHelper = $isOrderCreatedHelper;
    }

    public function execute(Observer $observer): void
    {
        if ($this->isOrderCreatedHelper->isCreated()) {
            return;
        }

        /** @var Quote $quote */
        $quote = $observer->getQuote();
        $quote->setIsActive(true);
    }
}
