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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\InvoiceOrder;
use Magento\Sales\Model\Service\InvoiceService;

class InvoiceCapture
{
    private PaymentOperationLock $lock;
    private ResourceConnection $resource;
    private InvoiceRepositoryInterface $invoices;
    private TransactionFactory $transactions;

    public function __construct(
        PaymentOperationLock $lock,
        ResourceConnection $resource,
        InvoiceRepositoryInterface $invoices,
        TransactionFactory $transactions
    ) {
        $this->lock = $lock;
        $this->resource = $resource;
        $this->invoices = $invoices;
        $this->transactions = $transactions;
    }

    public function aroundExecute($subject, callable $proceed, ...$args)
    {
        if ($subject instanceof InvoiceOrder) {
            $orderId = (int)$args[0];
        } else {
            $request = $subject->getRequest();
            $orderId = $request->getParam('invoice_id')
                ? $this->invoiceOrderId((int)$request->getParam('invoice_id'))
                : (int)$request->getParam('order_id');
        }
        return $this->withOrderLock($orderId, function () use ($proceed, $args) {
            return $proceed(...$args);
        });
    }

    public function aroundSetCapture(InvoiceService $subject, callable $proceed, $id)
    {
        return $this->withOrderLock($this->invoiceOrderId((int)$id), function () use ($proceed, $id) {
            $invoice = $this->invoices->get($id);
            $this->lock->assertOrderUnchanged($invoice->getOrder());
            $result = $proceed($id);
            // Native setCapture() only mutates objects. Persist before releasing the API lock.
            $this->transactions->create()->addObject($invoice)->addObject($invoice->getOrder())->save();
            return $result;
        }, function () use ($proceed, $id) {
            return $proceed($id);
        });
    }

    private function invoiceOrderId(int $invoiceId): int
    {
        $connection = $this->resource->getConnection('sales');
        return (int)$connection->fetchOne($connection->select()
            ->from($connection->getTableName('sales_invoice'), ['order_id'])
            ->where('entity_id = ?', $invoiceId));
    }

    private function withOrderLock(int $orderId, callable $operation, ?callable $otherMethod = null)
    {
        $connection = $this->resource->getConnection('sales');
        $method = (string)$connection->fetchOne($connection->select()
            ->from($connection->getTableName('sales_order_payment'), ['method'])
            ->where('parent_id = ?', $orderId));
        if (strpos($method, AbstractMethod::PAYMENT_PREFIX) !== 0 && $method !== Vault::CODE) {
            return ($otherMethod ?: $operation)();
        }
        return $this->lock->execute($orderId, $operation);
    }
}
