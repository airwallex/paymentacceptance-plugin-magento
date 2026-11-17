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
namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Observer\SalesOrderPaymentCancel;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderCancel
{
    private SalesOrderPaymentCancel $cancel;
    private OrderRepositoryInterface $orders;

    public function __construct(SalesOrderPaymentCancel $cancel, OrderRepositoryInterface $orders)
    {
        $this->cancel = $cancel;
        $this->orders = $orders;
    }

    public function aroundCancel($subject, callable $proceed, $id = null)
    {
        // Check before Payment::cancel() attempts a void and before cancellation mutates items.
        $order = $subject instanceof Order ? $subject : $this->orders->get($id);
        if ($order->canCancel() && !$this->cancel->cancel($order)) {
            return $subject instanceof Order ? $order : false;
        }
        return $subject instanceof Order ? $proceed() : $proceed($id);
    }
}
