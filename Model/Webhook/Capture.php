<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Model\Order;

class Capture extends AbstractWebhook
{
    use HelperTrait;

    /**
     * @deprecated No longer used. It is replaced by WEBHOOK_NAMES array.
     */
    public const WEBHOOK_NAME = 'payment_attempt.capture_requested';

    /**
     * Array of webhooks that trigger capture process.
     */
    public const WEBHOOK_NAMES = [
        // 'payment_attempt.capture_requested',
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
     * Capture constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(object $data): void
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->paymentIntentRepository->getOrder($paymentIntentId);

        if (!$order->getPayment() || $order->getTotalPaid()) {
            return;
        }

        $amount = $data->captured_amount;

        $grandTotal = $order->formatPrice($amount);
        $comment = sprintf('Captured amount of %s online. Transaction ID: \'%s\'.', $grandTotal, $paymentIntentId);
        $order->addCommentToStatusHistory(__($comment));
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);
        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$this->isAmountEqual(floatval($amount), floatval($order->getGrandTotal()))) {
            $invoice->setGrandTotal($amount);
            $targetAmount = $this->convertToDisplayCurrency($amount, $order->getBaseToOrderRate(), true);
            if ($targetAmount - $order->getBaseGrandTotal() >= 0.01) {
                $targetAmount = $order->getBaseGrandTotal();
            }
            $invoice->setBaseGrandTotal($targetAmount);
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
