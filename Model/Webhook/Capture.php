<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Model\Order;
use Magento\Framework\App\CacheInterface;

class Capture extends AbstractWebhook
{
    use HelperTrait;

    /**
     * Array of webhooks that trigger capture process.
     */
    public const WEBHOOK_NAMES = [
        'payment_intent.succeeded'
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

    /**
     * Capture constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CacheInterface $cache
     */
    public function __construct(
        OrderRepository         $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        InvoiceService          $invoiceService,
        TransactionFactory      $transactionFactory,
        CartRepositoryInterface $quoteRepository,
        CacheInterface          $cache
    )
    {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->cache = $cache;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(object $data): void
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;

        if ($this->cache->load($this->captureCacheName($paymentIntentId))) {
            $this->cache->remove($this->captureCacheName($paymentIntentId));
            return;
        }

        /** @var Order $order */
        $order = $this->paymentIntentRepository->getOrder($paymentIntentId);
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug(
                    '11'
                    );

        if (!$order->getPayment() || $order->getTotalPaid()) return;
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug(
            '22'
        );

        $order->setIsInProcess(true);
        $this->orderRepository->save($order);

        $amount = $data->captured_amount;
        $baseAmount = $this->getBaseAmount($amount, $order->getBaseToOrderRate(), $order->getGrandTotal(), $order->getBaseGrandTotal());
        $amountFormat = $this->priceForComment($amount, $baseAmount, $order);
        $comment = "Captured amount of $amountFormat through Airwallex. Transaction id: \"$paymentIntentId\".";
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
        \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug(
            '33'
        );

        // todo: request place-order 404 should return to success page
        // todo: test
        $quote = $this->quoteRepository->get($order->getQuoteId());
        if ($quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }
    }
}
