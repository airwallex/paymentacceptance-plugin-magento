<?php

namespace Airwallex\Payments\Logger;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Monolog\Logger as BaseLogger;

class Logger extends BaseLogger
{
    /**
     * @param Quote $quote
     * @param string $method
     * @param string $message
     *
     * @return void
     */
    public function quoteError(Quote $quote, string $method, string $message): void
    {
        $text = sprintf(
            '%s Quote: %s - %s',
            mb_strtoupper($method),
            $quote->getId(),
            $message
        );

        $this->error($text);
    }

    /**
     * @param Order $order
     * @param string $method
     * @param string $message
     *
     * @return void
     */
    public function orderError(Order $order, string $method, string $message): void
    {
        $text = sprintf(
            '%s Order: %s - %s',
            mb_strtoupper($method),
            'Order Increment ID: ' . $order->getIncrementId() . ' - Store ID: ' . $order->getStore()->getId(),
            $message
        );

        $this->error($text);
    }
}
