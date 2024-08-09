<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
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

    /**
     * @var CacheInterface
     */
    public CacheInterface $cache;

    /**
     * Capture constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        CacheInterface $cache
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
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

        if (!$order->getPayment() || $order->getTotalPaid()) {
            return;
        }

        $amount = $data->captured_amount;
        $baseAmount = $this->getBaseAmount($amount, $order->getBaseToOrderRate(), $order->getGrandTotal(), $order->getBaseGrandTotal());
        $grandTotalFormat = $order->formatPrice($amount);
        $baseGrandTotalFormat = $order->formatBasePrice($baseAmount);
        $amountFormat = $grandTotalFormat === $baseGrandTotalFormat ? $baseGrandTotalFormat : "$baseGrandTotalFormat ($grandTotalFormat)";
        $comment = "Captured amount of $amountFormat through Airwallex. Transaction ID: \"$paymentIntentId\".";
        $order->addCommentToStatusHistory(__($comment));
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);
        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$this->isAmountEqual($amount, $order->getGrandTotal())) {
            $invoice->setGrandTotal($amount);
            $invoice->setBaseGrandTotal($baseAmount);
        }
        $invoice->setTransactionId($paymentIntentId);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(true);
        $transactionSave = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();
    }
}
