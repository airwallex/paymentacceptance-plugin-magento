<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Cancel as CancelPaymentIntent;
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
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Airwallex\Payments\Logger\Logger;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;

class SalesOrderPaymentCancel implements ObserverInterface
{
    use HelperTrait;

    protected PaymentIntentRepository $paymentIntentRepository;
    protected CacheInterface $cache;
    protected CancelHelper $cancelHelper;
    protected CartRepositoryInterface $quoteRepository;
    protected CancelPaymentIntent $cancelPaymentIntent;
    protected Logger $logger;
    protected OrderFactory $orderFactory;
    protected OrderResourceInterface $orderResource;
    protected OrderManagementInterface $orderManagement;
    protected OrderRepositoryInterface $orderRepository;
    protected IntentHelper $intentHelper;
    protected RetrievePaymentIntent $retrievePaymentIntent;

    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        CacheInterface           $cache,
        CancelHelper             $cancelHelper,
        CartRepositoryInterface  $quoteRepository,
        CancelPaymentIntent      $cancelPaymentIntent,
        Logger                   $logger,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        IntentHelper             $intentHelper,
        RetrievePaymentIntent    $retrievePaymentIntent,
        CommonLibraryInit        $commonLibraryInit
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cache = $cache;
        $this->cancelHelper = $cancelHelper;
        $this->quoteRepository = $quoteRepository;
        $this->cancelPaymentIntent = $cancelPaymentIntent;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->intentHelper = $intentHelper;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $commonLibraryInit->exec();
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
        /** @var Order $order */
        $order = $observer->getPayment()->getOrder();
        $paymentIntentFromDB = $this->paymentIntentRepository->getByOrderId($order->getId());
        if (!$paymentIntentFromDB || $this->cancelHelper->isWebhookCanceling()) {
            return;
        }
        /** @var StructPaymentIntent $paymentIntentFromApi */
        $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($paymentIntentFromDB->getIntentId())->send();
        if ($paymentIntentFromApi->getStatus() === StructPaymentIntent::STATUS_CANCELLED) {
            return;
        }
        if ($paymentIntentFromApi->isCaptured()) {
            $quote = $this->quoteRepository->get($paymentIntentFromDB->getQuoteId());
            $this->changeOrderStatus($paymentIntentFromApi, $paymentIntentFromDB->getOrderId(), $quote, __METHOD__);
            $updatedOrder = $this->orderFactory->create();
            $this->orderResource->load($updatedOrder, $order->getId());
            $order->setPayment($updatedOrder->getPayment());
            $order->setTotalPaid($order->getGrandTotal());
            $order->setBaseTotalPaid($order->getBaseGrandTotal());

            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
            $order->setItems([]);
            return;
        }
        try {
            $this->cancelPaymentIntent->setPaymentIntentId($paymentIntentFromDB->getIntentId())->send();
        } catch (Exception $e) {
            RemoteLog::error(__METHOD__ . $e->getMessage(), 'onApiRequestError');
        }
    }
}
