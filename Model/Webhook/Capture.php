<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;

class Capture extends AbstractWebhook
{
    /**
     * @deprecated No longer used. It is replaced by WEBHOOK_NAMES array.
     */
    public const WEBHOOK_NAME = 'payment_attempt.capture_requested';

    /**
     * Array of webhooks that trigger capture process.
     */
    public const WEBHOOK_NAMES = [
        'payment_attempt.capture_requested',
        'payment_intent.succeeded'
    ];

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

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
        $order = $this->paymentIntentRepository->loadOrderByPaymentIntent($paymentIntentId);

        if ($order === null) {
            throw new WebhookException(__('Payment Intent: ' . $paymentIntentId . ': Can\'t find Order'));
        }

        $paid = $order->getBaseGrandTotal() - $order->getBaseTotalPaid();

        if ($paid === 0.0) {
            return;
        }

        $amount = $data->captured_amount;
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setBaseSubtotal($amount);
        $invoice->setBaseGrandTotal($amount);
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
