<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Airwallex\Payments\Logger\Logger;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Airwallex\Payments\Helper\IntentHelper;

class SalesOrderPaymentCancel implements ObserverInterface
{
    use HelperTrait;

    protected PaymentIntentRepository $paymentIntentRepository;
    protected ErrorLog $errorLog;
    protected CacheInterface $cache;
    protected CancelHelper $cancelHelper;
    protected CartRepositoryInterface $quoteRepository;
    protected Cancel $cancel;
    protected Logger $logger;
    protected Get $intentGet;
    protected OrderFactory $orderFactory;
    protected OrderResourceInterface $orderResource;
    protected OrderManagementInterface $orderManagement;
    protected OrderRepositoryInterface $orderRepository;
    protected IntentHelper $intentHelper;

    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        ErrorLog                 $errorLog,
        CacheInterface           $cache,
        CancelHelper             $cancelHelper,
        CartRepositoryInterface  $quoteRepository,
        Cancel                   $cancel,
        Logger                   $logger,
        Get                      $intentGet,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        IntentHelper             $intentHelper
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->errorLog = $errorLog;
        $this->cache = $cache;
        $this->cancelHelper = $cancelHelper;
        $this->quoteRepository = $quoteRepository;
        $this->cancel = $cancel;
        $this->logger = $logger;
        $this->intentGet = $intentGet;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->intentHelper = $intentHelper;
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
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $payment = $observer->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();

        $paymentIntent = $this->paymentIntentRepository->getByOrderId($order->getEntityId());
        if (!$paymentIntent) return;
        $intentId = $paymentIntent->getIntentId();
        if ($this->cancelHelper->isWebhookCanceling()) {
            return;
        }
        try {
            $this->cancel->setPaymentIntentId($intentId)->send();
        } catch (Exception $e) {
            if (strstr($e->getMessage(), 'CANCELLED')) {
                return;
            }
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $intentResponse = json_decode($resp, true);

            if (!empty($intentResponse['status']) && $intentResponse['status'] === PaymentIntentInterface::INTENT_STATUS_SUCCEEDED) {
                $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
                $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote, 'SalesOrderPaymentCancel');
                $updatedOrder = $this->orderFactory->create();
                $this->orderResource->load($updatedOrder, $order->getId());
                $order->setPayment($updatedOrder->getPayment());
                $order->setTotalPaid($order->getGrandTotal());
                $order->setBaseTotalPaid($order->getBaseGrandTotal());

                $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                $order->setItems([]);
            }
            $this->logger->orderError($payment->getOrder(), 'cancel', $e->getMessage());
            $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $intentId)->send();
        }
    }
}
