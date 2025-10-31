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
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;

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
    private OrderManagementInterface $orderManagement;
    private OrderFactory $orderFactory;
    private PaymentIntentRepository $paymentIntentRepository;
    private OrderRepositoryInterface $orderRepository;
    private OrderResourceInterface $orderResource;
    private RetrievePaymentIntent $retrievePaymentIntent;
    public IntentHelper $intentHelper;

    /**
     * Capture constructor.
     *
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CacheInterface $cache
     * @param OrderManagementInterface $orderManagement
     * @param OrderFactory $orderFactory
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderResourceInterface $orderResource
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param IntentHelper $intentHelper
     */
    public function __construct(
        InvoiceService           $invoiceService,
        TransactionFactory       $transactionFactory,
        CartRepositoryInterface  $quoteRepository,
        CacheInterface           $cache,
        OrderManagementInterface $orderManagement,
        OrderFactory             $orderFactory,
        PaymentIntentRepository  $paymentIntentRepository,
        OrderRepositoryInterface $orderRepository,
        OrderResourceInterface   $orderResource,
        RetrievePaymentIntent    $retrievePaymentIntent,
        IntentHelper             $intentHelper
    )
    {
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->cache = $cache;
        $this->orderManagement = $orderManagement;
        $this->orderFactory = $orderFactory;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderResource = $orderResource;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
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
        $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
        $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
        if (empty($order) || empty($order->getId())) {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
            return;
        }

        if (!$this->isOrderBeforePayment()) {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $this->changeOrderStatus($paymentIntentFromApi, $paymentIntent->getOrderId(), $quote, __METHOD__);
            $this->deactivateQuote($quote);
            return;
        }

        if ($data->captured_amount === $data->amount && $this->isOrderBeforePayment()) {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $this->changeOrderStatus($paymentIntentFromApi, $paymentIntent->getOrderId(), $quote, __METHOD__);
            return;
        }

        if (!$order->getPayment() || $order->getTotalPaid()) return;

        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        $amount = $data->captured_amount;
        $baseAmount = $this->getBaseAmount($amount, $order->getBaseToOrderRate(), $order->getGrandTotal(), $order->getBaseGrandTotal());
        $amountFormat = $this->priceForComment($amount, $baseAmount, $order);
        if ($data->currency === $order->getBaseCurrencyCode()) {
            $amountFormat = $order->formatBasePrice($amount);
            $baseAmount = $amount;
        } else if ($data->currency !== $order->getOrderCurrencyCode()) {
            $amount = round($data->captured_amount / $data->amount * $order->getBaseGrandTotal(), 2);
            $amountFormat = $order->formatBasePrice($amount);
            $baseAmount = $amount;
        }
        $comment = "Captured amount of $amountFormat through Airwallex. Transaction ID: \"$intentId\".";
        $this->addComment($order, $comment);
        $invoice = $this->invoiceService->prepareInvoice($order);
        if ($data->currency === $order->getOrderCurrencyCode() && !$this->isAmountEqual($amount, $order->getGrandTotal())) {
            $invoice->setGrandTotal($amount);
            $invoice->setBaseGrandTotal($baseAmount);
        }
        if ($data->currency !== $order->getOrderCurrencyCode() && !$this->isAmountEqual($amount, $order->getBaseGrandTotal())) {
            $invoice->setGrandTotal($this->convertToDisplayCurrency($amount, $order->getBaseToOrderRate(), false));
            $invoice->setBaseGrandTotal($amount);
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
