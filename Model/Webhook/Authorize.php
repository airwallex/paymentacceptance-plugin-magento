<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Methods\CardMethod;
use Airwallex\Payments\Model\Methods\ExpressCheckout;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Model\Order;
use Magento\Framework\App\CacheInterface;

class Authorize extends AbstractWebhook
{
    use HelperTrait;

    /**
     * Array of webhooks that trigger capture process.
     */
    public const WEBHOOK_NAMES = [
        'payment_intent.requires_capture'
    ];

    /**
     * @var InvoiceService
     */
    private InvoiceService $invoiceService;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    private CartRepositoryInterface $quoteRepository;

    /**
     * @var CacheInterface
     */
    public CacheInterface $cache;
    private OrderRepository $orderRepository;
    private PaymentIntentRepository $paymentIntentRepository;
    private Get $intentGet;

    /**
     * Capture constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param Get $intentGet
     * @param CacheInterface $cache
     */
    public function __construct(
        OrderRepository         $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        InvoiceService          $invoiceService,
        TransactionFactory      $transactionFactory,
        CartRepositoryInterface $quoteRepository,
        Get $intentGet,
        CacheInterface          $cache
    )
    {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->intentGet = $intentGet;
        $this->cache = $cache;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     * @throws GuzzleException
     */
    public function execute(object $data): void
    {
//        $paymentIntentId = $data->payment_intent_id ?? $data->id;
//        $paymentIntent = $this->paymentIntentRepository->getByIntentId($paymentIntentId);
//        /** @var Order $order */
//        $order = $this->orderRepository->get($paymentIntent->getOrderId());
//        $resp = $this->intentGet->setPaymentIntentId($paymentIntentId)->send();
//        $intentResponse = json_decode($resp, true);
//        $this->checkIntentWithOrder($intentResponse, $order);
//        $order->setIsInProcess(true);
//        $this->orderRepository->save($order);
//
//        $this->authorize($order, $intentResponse);
//
//        $quote = $this->quoteRepository->get($order->getQuoteId());
//        $quote->setIsActive(false);
//        $this->quoteRepository->save($quote);
    }
}
