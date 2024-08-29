<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
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

    public OrderGridFactory $orderGridFactory;
    public Log $errorLog;
    public HistoryFactory $historyFactory;
    public PaymentIntentRepository $paymentIntentRepository;

    public function __construct(
        OrderRepository $orderRepository,
        Get $intentGet,
        OrderGridFactory $orderGridFactory,
        Log $errorLog,
        HistoryFactory $historyFactory,
        PaymentIntentRepository $paymentIntentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->intentGet = $intentGet;
        $this->orderGridFactory = $orderGridFactory;
        $this->errorLog = $errorLog;
        $this->historyFactory = $historyFactory;
        $this->paymentIntentRepository = $paymentIntentRepository;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        // todo
        /** @var Order $order */
        $order = $observer->getOrder();
        $method = $order->getPayment()->getMethod();
        if (strpos($method, 'airwallex') !== 0) return;

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
    }


}
