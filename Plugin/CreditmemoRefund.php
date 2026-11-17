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

use Airwallex\Payments\Model\Methods\AbstractMethod;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\PaymentOperationLock;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\RefundInvoice;
use Magento\Sales\Model\Service\CreditmemoService;

class CreditmemoRefund
{
    private PaymentOperationLock $paymentOperationLock;
    private InvoiceRepositoryInterface $invoices;
    private OrderRepositoryInterface $orders;

    public function __construct(
        PaymentOperationLock $paymentOperationLock,
        InvoiceRepositoryInterface $invoices,
        OrderRepositoryInterface $orders
    ) {
        $this->paymentOperationLock = $paymentOperationLock;
        $this->invoices = $invoices;
        $this->orders = $orders;
    }

    public function aroundRefund(
        CreditmemoService $subject,
        callable $proceed,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        return $this->withOrderLock($creditmemo->getOrder(), function () use ($proceed, $creditmemo, $offlineRequested) {
            return $proceed($creditmemo, $offlineRequested);
        });
    }

    public function aroundExecute($subject, callable $proceed, ...$args)
    {
        $orderId = $subject instanceof RefundInvoice
            ? $this->invoices->get($args[0])->getOrderId()
            : $args[0];
        return $this->withOrderLock($this->orders->get($orderId), function () use ($proceed, $args) {
            return $proceed(...$args);
        });
    }

    private function withOrderLock(OrderInterface $order, callable $operation)
    {
        $payment = $order->getPayment();
        $method = $payment ? (string)$payment->getMethod() : '';
        if (strpos($method, AbstractMethod::PAYMENT_PREFIX) !== 0 && $method !== Vault::CODE) {
            return $operation();
        }
        return $this->paymentOperationLock->execute((int)$order->getEntityId(), function () use ($order, $operation) {
            $this->paymentOperationLock->assertOrderUnchanged($order);
            return $operation();
        });
    }
}
