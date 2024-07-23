<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Grid\CollectionFactory as OrderGridFactory;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\Client\Request\Log;
use Magento\Sales\Model\Order\Status\HistoryFactory;

class CheckoutSubmitAllAfter implements ObserverInterface
{
    use HelperTrait;

    /**
     * @var OrderRepository
     */
    public OrderRepository $orderRepository;

    /**
     * @var Get
     */
    public Get $intentGet;

    public OrderFactory $orderFactory;
    public OrderGridFactory $orderGridFactory;
    public Log $errorLog;
    public HistoryFactory $historyFactory;
    public PaymentIntentRepository $paymentIntentRepository;

    public function __construct(
        OrderRepository $orderRepository,
        Get $intentGet,
        OrderFactory $orderFactory,
        OrderGridFactory $orderGridFactory,
        Log $errorLog,
        HistoryFactory $historyFactory,
        PaymentIntentRepository $paymentIntentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->intentGet = $intentGet;
        $this->orderFactory = $orderFactory;
        $this->orderGridFactory = $orderGridFactory;
        $this->errorLog = $errorLog;
        $this->historyFactory = $historyFactory;
        $this->paymentIntentRepository = $paymentIntentRepository;
    }
    /**
     * @param Observer $observer
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();

        $method = $order->getPayment()->getMethod();
        if (!strstr($method, 'airwallex_')) return;

        /** @var string $intentId */
        if ($intentId = $order->getPayment()->getAdditionalInformation('intent_id')) {
            $this->addAVSResultToOrder($order, $intentId);
        }
        if ($this->isRedirectMethodConstant($order->getPayment()->getMethod())) {
            $comment = sprintf(
                'Status changed to %s, payment method is %s.',
                Order::STATE_PENDING_PAYMENT,
                $order->getPayment()->getMethod()
            );
            $order->addCommentToStatusHistory(__($comment));
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
            $this->orderRepository->save($order);
        }

        $quoteId = $order->getQuoteId();
        if (empty($quoteId)) return;

        $orderCollection = $this->orderFactory->create()->addFieldToFilter('quote_id', $quoteId);
        if (count($orderCollection) > 1) {
            $record = $this->paymentIntentRepository->getByQuoteId($quoteId);
            if (!$record || !$record->getPaymentIntentId()) return;
            $detail = $record->getDetail();
            $detailArray = $detail ? json_decode($detail, true) : [];
            foreach ($orderCollection as $orderItem) {
                if ($orderItem->getIncrementId() !== $record->getOrderIncrementId()
                    && $orderItem->getPayment()->getAdditionalInformation('intent_id') === $record->getPaymentIntentId()) {
                    $orderGrid = [];
                    try {
                        $orderGridCollection = $this->orderGridFactory->create()->addFieldToFilter('entity_id', $orderItem->getEntityId());
                        foreach ($orderGridCollection as $orderGridItem) {
                            $orderGrid = $orderGridItem->getData();
                        }
                    } catch(\Exception $e) {};


                    if (empty($detailArray['repeat_orders'])) {
                        $detailArray['repeat_orders'] = [];
                    }
                    $detailItem = [
                        'order' => $orderItem->getData(),
                        'order_grid' => $orderGrid,
                    ];
                    $detailArray['repeat_orders'][] = $detailItem;
                    $this->errorLog->setMessage('repeat_order', base64_encode(json_encode($detailItem)))->send();
                    $this->paymentIntentRepository->updateDetail($record, json_encode($detailArray));
                    $this->orderRepository->delete($orderItem);
                }
            }
        }
    }


    private function addAVSResultToOrder(Order $order, string $intentId)
    {
        $histories = $order->getStatusHistories();
        if (!$histories) {
            return;
        }
        $log = $src = '[Verification] ';
        foreach ($histories as $history) {
            $history->getComment();
            if (strstr($history->getComment(), $log)) return;
        }
        try {
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $respArr = json_decode($resp, true);
            $brand = $respArr['latest_payment_attempt']['payment_method']['card']['brand'] ?? '';
            if ($brand) $brand = ' Card Brand: ' . strtoupper($brand) . '.';
            $last4 = $respArr['latest_payment_attempt']['payment_method']['card']['last4'] ?? '';
            if ($last4) $last4 = ' Card Last Digits: ' . $last4 . '.';
            $avs_check = $respArr['latest_payment_attempt']['authentication_data']['avs_result'] ?? '';
            if ($avs_check) $avs_check = ' AVS Result: ' . $avs_check . '.';
            $cvc_check = $respArr['latest_payment_attempt']['authentication_data']['cvc_result'] ?? '';
            if ($cvc_check) $cvc_check = ' CVC Result: ' . $cvc_check . '.';
            $log .= $brand . $last4 . $avs_check . $cvc_check;
            if ($log === $src) return;
            $latestOrder = $this->orderRepository->get($order->getEntityId());
            $history = $this->historyFactory->create()
                ->setParentId($order->getEntityId())
                ->setComment(__($log))
                ->setEntityName('order')
                ->setStatus($latestOrder->getStatus())
                ->save();
        } catch (\Exception $e) {
        }
    }
}
