<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\HistoryFactory;

class OrderCancelAfter implements ObserverInterface
{
    use HelperTrait;

    private Cancel $cancel;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected ErrorLog $errorLog;
    protected HistoryFactory $historyFactory;

    public function __construct(
        PaymentIntentRepository $paymentIntentRepository,
        Cancel $cancel,
        OrderRepositoryInterface $orderRepository,
        ErrorLog $errorLog,
        HistoryFactory $historyFactory
    ) {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->cancel = $cancel;
        $this->errorLog = $errorLog;
        $this->historyFactory = $historyFactory;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        if ($this->isRedirectMethodConstant($order->getPayment()->getMethod())) {
            $record = $this->paymentIntentRepository->getByOrderIncrementIdAndStoreId($order->getIncrementId(), $order->getStoreId());
            try {
                $this->cancel->setPaymentIntentId($record->getPaymentIntentId())->send();
                $this->historyFactory->create()
                    ->setParentId($order->getEntityId())
                    ->setComment(__('Order cancelled through Airwallex.'))
                    ->setEntityName('order')
                    ->setStatus($order->getStatus())
                    ->save();
            } catch (Exception $e) {
                $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $order->getIncrementId())->send();
            }
        }
    }
}
