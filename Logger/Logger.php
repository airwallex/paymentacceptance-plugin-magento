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
     * @return bool
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
     * @return bool
     */
    public function orderError(Order $order, string $method, string $message): void
    {
        $text = sprintf(
            '%s Order: %s - %s',
            mb_strtoupper($method),
            'Order Increment ID: ' . $order->getIncrementId() . ' - Store ID: ' . (string)$order->getStore()->getId(),
            $message
        );

        $this->error($text);
    }
}
