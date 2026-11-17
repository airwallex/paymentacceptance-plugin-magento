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

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as PaymentIntentResource;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent\CollectionFactory as PaymentIntentCollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class PaymentIntentRepository
{
    /**
     * @var PaymentIntentCollectionFactory
     */
    private PaymentIntentCollectionFactory $paymentIntentCollectionFactory;

    /**
     * @var PaymentIntentFactory
     */
    private PaymentIntentFactory $paymentIntentFactory;

    /**
     * @var PaymentIntentResource
     */
    private PaymentIntentResource $paymentIntentResource;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    private ?ConnectionFactory $connectionFactory;

    private $independentConnection = null;

    /**
     * PaymentIntentRepository constructor.
     *
     * @param PaymentIntentCollectionFactory $paymentIntentCollectionFactory
     * @param PaymentIntentFactory $paymentIntentFactory
     * @param PaymentIntentResource $paymentIntentResource
     * @param Order $order
     * @param OrderFactory $orderFactory
     * @param ConnectionFactory|null $connectionFactory
     */
    public function __construct(
        PaymentIntentCollectionFactory $paymentIntentCollectionFactory,
        PaymentIntentFactory           $paymentIntentFactory,
        PaymentIntentResource          $paymentIntentResource,
        Order                          $order,
        OrderFactory                   $orderFactory,
        ?ConnectionFactory             $connectionFactory = null
    )
    {
        $this->paymentIntentCollectionFactory = $paymentIntentCollectionFactory;
        $this->paymentIntentFactory = $paymentIntentFactory;
        $this->paymentIntentResource = $paymentIntentResource;
        $this->order = $order;
        $this->orderFactory = $orderFactory;
        $this->connectionFactory = $connectionFactory;
    }


    /**
     * @param string $intentId
     *
     * @return PaymentIntentInterface
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getByIntentId(string $intentId): PaymentIntentInterface
    {
        if (!$intentId) {
            throw new InputException(__('Payment intent id is required.'));
        }

        $collection = $this->paymentIntentCollectionFactory->create();
        $collection->addFieldToFilter(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN, $intentId);
        $collection->setOrder('id', 'DESC');
        $collection->setPageSize(1);

        $paymentIntent = $collection->getFirstItem();

        if (!$paymentIntent->getId()) {
            throw new NoSuchEntityException(__('The payment intent "%1" does not exist.', $intentId));
        }

        return $paymentIntent;
    }

    /**
     * @param int $quoteId
     *
     * @return ?PaymentIntentInterface
     * @throws InputException
     */
    public function getByQuoteId(int $quoteId): ?PaymentIntentInterface
    {
        if ($quoteId <= 0) {
            throw new InputException(__('Invalid quote id.'));
        }

        $collection = $this->paymentIntentCollectionFactory->create();
        $collection->addFieldToFilter(PaymentIntentInterface::QUOTE_ID_COLUMN, $quoteId);
        $collection->setOrder('id', 'DESC');

        $paymentIntent = $collection->getFirstItem();

        if (!$paymentIntent->getId()) {
            return null;
        }

        return $paymentIntent;
    }

    /**
     * @param int $orderId
     *
     * @return ?PaymentIntentInterface
     * @throws InputException
     */
    public function getByOrderId(int $orderId): ?PaymentIntentInterface
    {
        if ($orderId <= 0) {
            throw new InputException(__('Invalid order id.'));
        }

        $collection = $this->paymentIntentCollectionFactory->create();
        $collection->addFieldToFilter(PaymentIntentInterface::ORDER_ID_COLUMN, $orderId);
        $collection->setOrder('id', 'DESC');

        $paymentIntent = $collection->getFirstItem();

        if (!$paymentIntent->getId()) {
            return null;
        }

        return $paymentIntent;
    }

    /**
     * @param string $orderIncrementId
     * @param int $storeId
     *
     * @return ?PaymentIntentInterface
     * @throws InputException
     * @throws LocalizedException
     */
    public function getByOrderIncrementIdAndStoreId(string $orderIncrementId, int $storeId): ?PaymentIntentInterface
    {
        if (!$orderIncrementId) {
            throw new InputException(__('Order increment id is required.'));
        }
        if ($storeId <= 0) {
            throw new InputException(__('Invalid store id.'));
        }
        $paymentIntent = $this->paymentIntentFactory->create();
        $connection = $this->paymentIntentResource->getConnection();
        $orderIncrementIdName = PaymentIntentInterface::ORDER_INCREMENT_ID_COLUMN;
        $storeIdName = PaymentIntentInterface::STORE_ID_COLUMN;
        $bind = [$orderIncrementIdName => $orderIncrementId, $storeIdName => $storeId];
        $select = $connection->select()
            ->from($this->paymentIntentResource->getMainTable())
            ->where($orderIncrementIdName . ' = :' . $orderIncrementIdName)
            ->where($storeIdName . ' = :' . $storeIdName)
            ->order("id DESC")
            ->limit(1);

        $data = $connection->fetchRow($select, $bind);

        if (!$data) {
            return null;
        }

        $paymentIntent->setData($data);

        return $paymentIntent;
    }

    /**
     * @param string $paymentIntentId
     *
     * @return OrderInterface|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function getOrder(string $paymentIntentId): ?Order
    {
        $record = $this->getByIntentId($paymentIntentId);
        $order = $this->orderFactory->create();
        if ($record->getOrderId()) {
            $order->load($record->getOrderId());
        } else {
            $order->loadByIncrementIdAndStoreId($record->getOrderIncrementId(), $record->getStoreId());
        }
        if (!$order->getId()) {
            return null;
        }
        if ((int) $order->getStoreId() !== (int) $record->getStoreId()
            || (int) $order->getQuoteId() !== (int) $record->getQuoteId()
            || $order->getIncrementId() !== $record->getOrderIncrementId()
        ) {
            throw new InputException(__('Order does not match the payment intent.'));
        }
        return $order;
    }

    public function withLockedIntent(string $intentId, callable $operation)
    {
        $connection = $this->paymentIntentResource->getConnection();
        $connection->beginTransaction();
        try {
            // The reconciliation and its deduplication marker commit together.
            $select = $connection->select()
                ->from($this->paymentIntentResource->getMainTable(), ['id', 'detail'])
                ->where(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN . ' = ?', $intentId)
                ->order('id DESC')
                ->limit(1)
                ->forUpdate(true);
            $data = $connection->fetchRow($select);
            if (!$data) {
                throw new NoSuchEntityException(__('Payment intent does not exist.'));
            }
            $record = $this->getByIntentId($intentId);
            if ((int) $record->getId() !== (int) $data['id']) {
                throw new LocalizedException(__('Payment intent changed. Please retry.'));
            }
            // A locking read sees the latest committed detail even when the caller
            // already established a repeatable-read snapshot in an outer transaction.
            $record->setDetail($data['detail'] ?? '');
            $result = $operation($record);
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function hasRecordedRefund(PaymentIntentInterface $record, string $refundId): bool
    {
        $detail = $this->decodeDetail($record->getDetail() ?? '');
        return in_array($refundId, $this->refundIdsFromDetail($detail), true);
    }

    public function appendRefundToRecord(PaymentIntentInterface $record, string $refundId, string $requestId = ''): void
    {
        $detail = $this->decodeDetail($record->getDetail() ?? '');
        $ids = $this->refundIdsFromDetail($detail);
        if (!in_array($refundId, $ids, true)) {
            $ids[] = $refundId;
        }
        $detail['refund_ids'] = $ids;
        if ($requestId !== '' && ($detail['refund_request_id'] ?? '') === $requestId) {
            $detail['refund_request_id'] = '';
        }
        $this->saveMergedDetail($record, $detail);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function updateDetail($paymentIntent, $detail): void
    {
        $this->withLockedIntent($paymentIntent->getIntentId(), function ($current) use ($paymentIntent, $detail): void {
            $incoming = json_decode($detail, true);
            if (!is_array($incoming)) {
                throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
            }
            $this->saveMergedDetail($current, $incoming);
            $paymentIntent->setDetail($current->getDetail());
        });
    }

    private function saveMergedDetail(PaymentIntentInterface $record, array $incoming): void
    {
        $stored = $this->decodeDetail($record->getDetail() ?? '');
        if ((isset($incoming['refund_ids']) && !is_array($incoming['refund_ids']))
            || (isset($stored['refund_ids']) && !is_array($stored['refund_ids']))
        ) {
            throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
        }
        if ((isset($stored['refund_request_id']) && !is_string($stored['refund_request_id']))
            || (isset($incoming['refund_request_id']) && !is_string($incoming['refund_request_id']))
        ) {
            throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
        }
        if (isset($stored['refund_ids']) || isset($incoming['refund_ids'])) {
            $incoming['refund_ids'] = array_values(array_unique(array_merge(
                $stored['refund_ids'] ?? [], $incoming['refund_ids'] ?? []
            )));
        }
        if (!array_key_exists('refund_request_id', $incoming) && !empty($stored['refund_request_id'])) {
            $incoming['refund_request_id'] = $stored['refund_request_id'];
        }
        $encoded = json_encode($incoming);
        if ($encoded === false) {
            throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
        }
        $record->setDetail($encoded);
        $this->paymentIntentResource->save($record);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function updateOrderId($paymentIntent, $orderId): void
    {
        $this->withLockedIntent($paymentIntent->getIntentId(), function ($current) use ($paymentIntent, $orderId): void {
            $current->setOrderId($orderId);
            $this->paymentIntentResource->save($current);
            $paymentIntent->setOrderId($orderId);
        });
    }

    /**
     * @throws AlreadyExistsException
     */
    public function updateMethodCodes($paymentIntent, $codes): void
    {
        $this->withLockedIntent($paymentIntent->getIntentId(), function ($current) use ($paymentIntent, $codes): void {
            $current->setMethodCodes($codes);
            $this->paymentIntentResource->save($current);
            $paymentIntent->setMethodCodes($codes);
        });
    }

    private function appendCodes($codes, $code)
    {
        if (empty($code)) return $codes;
        $target = [];
        if (!empty($codes)) {
            $target = json_decode($codes, true);
        }
        $target[] = $code;
        return json_encode($target);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function appendMethodCode($paymentIntent, $code): void
    {
        $codes = $paymentIntent->getMethodCodes();
        $this->updateMethodCodes($paymentIntent, $this->appendCodes($codes, $code));
    }

    /**
     * @throws AlreadyExistsException
     */
    public function lastMethodCode($paymentIntent): string
    {
        if (empty($paymentIntent)) return "";
        $codes = $paymentIntent->getMethodCodes();
        if (empty($codes)) return '';
        $arrCodes = json_decode($codes, true);
        return end($arrCodes);
    }

    /**
     * @param string $orderIncrement
     * @param string $paymentIntentId
     * @param string $currencyCode
     * @param float $grandTotal
     * @param int $orderId
     * @param int $quoteId
     * @param int $storeId
     * @param string $detail
     * @param string $codes
     * @return void
     * @throws AlreadyExistsException
     */
    public function save(
        string $orderIncrement,
        string $paymentIntentId,
        string $currencyCode,
        float  $grandTotal,
        int    $orderId,
        int    $quoteId,
        int    $storeId,
        string $detail,
        string $codes
    ): void
    {
        $paymentIntent = $this->paymentIntentFactory->create();
        $paymentIntent->setPaymentIntentId($paymentIntentId)
            ->setOrderIncrementId($orderIncrement)
            ->setCurrencyCode($currencyCode)
            ->setGrandTotal($grandTotal)
            ->setOrderId($orderId)
            ->setQuoteId($quoteId)
            ->setStoreId($storeId)
            ->setMethodCodes($codes)
            ->setDetail($detail);

        $this->paymentIntentResource->save($paymentIntent);
    }

    public function reserveRefundRequest(string $intentId): string
    {
        $result = $this->withIndependentIntent($intentId, function (array $detail): array {
            $requestId = is_string($detail['refund_request_id'] ?? null) ? $detail['refund_request_id'] : '';
            $changed = false;
            if ($requestId === '') {
                $requestId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
                $detail['refund_request_id'] = $requestId;
                $changed = true;
            }
            return [
                'request_id' => $requestId,
                'detail_changed' => $changed,
                'detail' => $detail,
            ];
        });
        return (string)$result['request_id'];
    }

    public function clearRefundRequest(string $intentId, string $requestId): void
    {
        $this->withIndependentIntent($intentId, function (array $detail) use ($requestId): array {
            if (($detail['refund_request_id'] ?? '') !== $requestId) {
                return ['detail_changed' => false];
            }
            $detail['refund_request_id'] = '';
            return [
                'detail_changed' => true,
                'detail' => $detail,
            ];
        });
    }

    private function withIndependentIntent(string $intentId, callable $mutate): array
    {
        $connection = $this->independentConnection();
        $connection->beginTransaction();
        try {
            $table = $this->paymentIntentResource->getMainTable();
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($table, ['id', 'detail'])
                    ->where(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN . ' = ?', $intentId)
                    ->order('id DESC')
                    ->limit(1)
                    ->forUpdate(true)
            );
            if (!$row) {
                throw new NoSuchEntityException(__('Payment intent does not exist.'));
            }
            $detail = $this->decodeDetail($row['detail'] ?? '');
            $result = $mutate($detail);
            if (!empty($result['detail_changed'])) {
                $encoded = json_encode($result['detail'] ?? []);
                if ($encoded === false) {
                    throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
                }
                $connection->update($table, ['detail' => $encoded], ['id = ?' => $row['id']]);
            }
            $connection->commit();
            unset($result['detail'], $result['detail_changed']);
            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function independentConnection(): AdapterInterface
    {
        if ($this->independentConnection instanceof AdapterInterface) {
            return $this->independentConnection;
        }
        $connectionFactory = $this->connectionFactory ?: ObjectManager::getInstance()->get(ConnectionFactory::class);
        $config = $this->paymentIntentResource->getConnection()->getConfig();
        if (!is_array($config)) {
            throw new LocalizedException(__('Database connection is not configured.'));
        }
        // A connected Magento adapter splits host into port or unix_socket; the factory expects host.
        if (!empty($config['unix_socket'])) {
            $config['host'] = $config['unix_socket'];
        } elseif (!empty($config['host']) && !empty($config['port'])) {
            $config['host'] .= ':' . $config['port'];
        }
        unset($config['port']);
        if (empty($config['host'])) {
            throw new LocalizedException(__('Database connection is not configured.'));
        }
        $this->independentConnection = $connectionFactory->create($config);
        return $this->independentConnection;
    }

    private function decodeDetail($detail): array
    {
        if (!is_string($detail) || $detail === '') {
            return [];
        }
        $decoded = json_decode($detail, true);
        if (!is_array($decoded)) {
            throw new LocalizedException(__('Invalid payment intent reconciliation data.'));
        }
        return $decoded;
    }

    private function refundIdsFromDetail(array $detail): array
    {
        $recorded = $detail['refund_ids'] ?? [];
        if (!is_array($recorded)) {
            throw new LocalizedException(__('Invalid refund reconciliation data.'));
        }
        return $recorded;
    }
}
