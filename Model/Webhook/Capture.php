<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Model\Order;
use Magento\Framework\App\CacheInterface;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;

class Capture extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_NAMES = [
        'payment_intent.succeeded'
    ];

    private InvoiceService $invoiceService;
    private TransactionFactory $transactionFactory;
    private CartRepositoryInterface $quoteRepository;
    public CacheInterface $cache;
    private Get $intentGet;
    private OrderManagementInterface $orderManagement;
    private OrderFactory $orderFactory;
    private PaymentIntentRepository $paymentIntentRepository;
    private OrderRepositoryInterface $orderRepository;
    private OrderResourceInterface $orderResource;
    public ErrorLog $errorLog;
    public IntentHelper $intentHelper;

    /**
     * Capture constructor.
     *
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CacheInterface $cache
     * @param Get $intentGet
     * @param OrderManagementInterface $orderManagement
     * @param OrderFactory $orderFactory
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderResourceInterface $orderResource
     * @param ErrorLog $errorLog
     * @param IntentHelper $intentHelper
     */
    public function __construct(
        InvoiceService           $invoiceService,
        TransactionFactory       $transactionFactory,
        CartRepositoryInterface  $quoteRepository,
        CacheInterface           $cache,
        Get                      $intentGet,
        OrderManagementInterface $orderManagement,
        OrderFactory             $orderFactory,
        PaymentIntentRepository  $paymentIntentRepository,
        OrderRepositoryInterface $orderRepository,
        OrderResourceInterface   $orderResource,
        ErrorLog                 $errorLog,
        IntentHelper             $intentHelper
    )
    {
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->cache = $cache;
        $this->intentGet = $intentGet;
        $this->orderManagement = $orderManagement;
        $this->orderFactory = $orderFactory;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderResource = $orderResource;
        $this->errorLog = $errorLog;
        $this->intentHelper = $intentHelper;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws LocalizedException
     * @throws GuzzleException
     * @throws JsonException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function execute(object $data): void
    {
        $intentId = $data->payment_intent_id ?? $data->id;

        if ($this->cache->load($this->captureCacheName($intentId))) {
            $this->cache->remove($this->captureCacheName($intentId));
            return;
        }

        /** @var Order $order */
        $order = $this->paymentIntentRepository->getOrder($intentId);
        if ($this->isAmountEqual($order->getGrandTotal(), $data->captured_amount) && $order->getStatus() !== Order::STATE_PROCESSING) {
            $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $intentResponse = json_decode($resp, true);
            $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
            $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote, 'webhook capture');
            return;
        }

        if (!$order->getPayment() || $order->getTotalPaid()) return;

        $order->setIsInProcess(true);
        $this->orderRepository->save($order);

        $amount = $data->captured_amount;
        $baseAmount = $this->getBaseAmount($amount, $order->getBaseToOrderRate(), $order->getGrandTotal(), $order->getBaseGrandTotal());
        $amountFormat = $this->priceForComment($amount, $baseAmount, $order);
        $comment = "Captured amount of $amountFormat through Airwallex. Transaction id: \"$intentId\".";
        $this->addComment($order, $comment);
        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$this->isAmountEqual($amount, $order->getGrandTotal())) {
            $invoice->setGrandTotal($amount);
            $invoice->setBaseGrandTotal($baseAmount);
        }
        $invoice->setTransactionId($data->id);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transactionSave = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();
    }
}
