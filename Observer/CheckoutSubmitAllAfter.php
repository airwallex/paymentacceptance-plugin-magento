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

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class CheckoutSubmitAllAfter implements ObserverInterface
{
    use HelperTrait;
    /**
     * @param Observer $observer
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getOrder();
        if ($this->isRedirectMethodConstant($order->getPayment()->getMethod())) {
            $comment = sprintf('Status changed to %s, payment method is %s.', Order::STATE_PENDING_PAYMENT, 
                $order->getPayment()->getMethod());
            $order->addCommentToStatusHistory(__($comment));
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT)->save();
        }
    }
}
