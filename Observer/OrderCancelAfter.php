<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;

class OrderCancelAfter implements ObserverInterface
{
    use HelperTrait;

    private Cancel $cancel;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected ErrorLog $errorLog;

    public function __construct(
        PaymentIntentRepository $paymentIntentRepository,
        Cancel $cancel,
        OrderRepositoryInterface $orderRepository,
        ErrorLog $errorLog
    ) {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->cancel = $cancel;
        $this->errorLog = $errorLog;
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
        if ($this->isRedirectMethodConstant($order->getPayment()->getMethod())) {
            $record = $this->paymentIntentRepository->getByOrderIncrementIdAndStoreId($order->getIncrementId(), $order->getStoreId());
            try {
                $this->cancel->setPaymentIntentId($record->getPaymentIntentId())->send();
                $order->addCommentToStatusHistory(__('Order cancelled through Airwallex.'));
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $order->getIncrementId())->send();
            }
        }
    }
}
