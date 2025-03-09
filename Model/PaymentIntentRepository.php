<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as PaymentIntentResource;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent\CollectionFactory as PaymentIntentCollectionFactory;
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

    /**
     * PaymentIntentRepository constructor.
     *
     * @param PaymentIntentCollectionFactory $paymentIntentCollectionFactory
     * @param PaymentIntentFactory $paymentIntentFactory
     * @param PaymentIntentResource $paymentIntentResource
     * @param Order $order
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        PaymentIntentCollectionFactory $paymentIntentCollectionFactory,
        PaymentIntentFactory           $paymentIntentFactory,
        PaymentIntentResource          $paymentIntentResource,
        Order                          $order,
        OrderFactory                   $orderFactory
    )
    {
        $this->paymentIntentCollectionFactory = $paymentIntentCollectionFactory;
        $this->paymentIntentFactory = $paymentIntentFactory;
        $this->paymentIntentResource = $paymentIntentResource;
        $this->order = $order;
        $this->orderFactory = $orderFactory;
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
        $order = $this->order->loadByIncrementIdAndStoreId($record->getOrderIncrementId(), $record->getStoreId());
        if (!$order || !$order->getId()) {
            $order = $this->orderFactory->create();
            $order = $order->loadByIncrementId($record->getOrderIncrementId());
        }
        return $order;
    }

    /**
     * @throws AlreadyExistsException
     */
    public function updateDetail($paymentIntent, $detail): void
    {
        $paymentIntent->setDetail($detail);
        $this->paymentIntentResource->save($paymentIntent);
    }
    /**
     * @throws AlreadyExistsException
     */
    public function updateOrderId($paymentIntent, $orderId): void
    {
        $paymentIntent->setOrderId($orderId);
        $this->paymentIntentResource->save($paymentIntent);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function updateMethodCodes($paymentIntent, $codes): void
    {
        $paymentIntent->setMethodCodes($codes);
        $this->paymentIntentResource->save($paymentIntent);
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
}
