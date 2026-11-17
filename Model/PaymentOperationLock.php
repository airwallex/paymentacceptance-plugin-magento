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
namespace Airwallex\Payments\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

class PaymentOperationLock
{
    public const ORDER_COMPLETION_TIMEOUT = 10;
    private LockManagerInterface $locks;
    private ResourceConnection $resource;
    private array $held = [];

    public function __construct(LockManagerInterface $locks, ResourceConnection $resource)
    {
        $this->locks = $locks;
        $this->resource = $resource;
    }

    public function isHeld(int $orderId): bool
    {
        return isset($this->held[$orderId]);
    }

    public function assertHeld(int $orderId): void
    {
        if (!$this->isHeld($orderId)) {
            throw new LocalizedException(__('Airwallex payment operations must use a locked invoice or credit memo service.'));
        }
    }

    public function assertOrderUnchanged(\Magento\Sales\Model\Order $order): void
    {
        $this->assertHeld((int)$order->getId());
        $connection = $this->resource->getConnection('sales');
        $fields = ['state', 'total_paid', 'base_total_paid', 'total_invoiced', 'base_total_invoiced',
            'total_refunded', 'base_total_refunded'];
        $row = $connection->fetchRow($connection->select()
            ->from($connection->getTableName('sales_order'), $fields)
            ->where('entity_id = ?', (int)$order->getId())->forUpdate(true));
        if (!$row || (string)$row['state'] === \Magento\Sales\Model\Order::STATE_CANCELED) {
            throw new LocalizedException(__('The order changed. Please reload it before retrying.'));
        }
        // Native invoice registration updates in-memory totals before calling capture().
        foreach (array_slice($fields, 1) as $field) {
            if (round((float)$row[$field], 4) !== round((float)$order->getOrigData($field), 4)) {
                throw new LocalizedException(__('The order changed. Please reload it before retrying.'));
            }
        }
    }

    public function execute(int $orderId, callable $operation, int $timeout = 0)
    {
        if (isset($this->held[$orderId])) {
            return $operation();
        }
        // Acquire before the sales transaction; release only after its commit or rollback.
        if ($orderId <= 0 || $this->resource->getConnection('sales')->getTransactionLevel() > 0) {
            throw new LocalizedException(__('Airwallex payment operations must start outside an existing sales transaction.'));
        }
        $name = 'awx_payment_order_' . $orderId;
        if (!$this->locks->lock($name, $timeout)) {
            throw new LocalizedException(__('Another payment operation is in progress. Please reload the order and retry.'));
        }
        $this->held[$orderId] = true;
        try {
            return $operation();
        } finally {
            unset($this->held[$orderId]);
            $this->locks->unlock($name);
        }
    }
}
