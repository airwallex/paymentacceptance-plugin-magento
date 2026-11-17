<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Model\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;
use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\PaymentOperationLock;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\CreditmemoService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;

class Refund extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_SUCCESS_NAME = 'refund.accepted';

    /**
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;

    /**
     * @var PaymentIntentRepository
     */
    private PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    private CreditmemoService $creditmemoService;

    /**
     * @var RetrievePaymentIntent
     */
    private RetrievePaymentIntent $retrievePaymentIntent;

    private PaymentOperationLock $paymentOperationLock;

    /**
     * Refund constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param PaymentOperationLock $paymentOperationLock
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        RetrievePaymentIntent $retrievePaymentIntent,
        PaymentOperationLock $paymentOperationLock
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $this->paymentOperationLock = $paymentOperationLock;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     */
    public function execute(object $data): void
    {
        $intentId = $data->payment_intent_id ?? null;
        $refundId = $data->id ?? null;
        $amount = $data->amount ?? null;
        $currency = $data->currency ?? null;
        if (!is_string($intentId) || $intentId === '' || !is_string($refundId) || $refundId === ''
            || !is_numeric($amount) || !is_finite((float) $amount) || (float) $amount <= 0
            || !is_string($currency) || !preg_match('/^[A-Z]{3}$/D', $currency)
        ) {
            throw new WebhookException(__('Invalid refund event.'));
        }
        $order = $this->paymentIntentRepository->getOrder($intentId);
        if (!$order || !$order->getId()) {
            throw new WebhookException(__("Can't find order"));
        }
        $this->paymentOperationLock->execute((int)$order->getId(), function () use ($data, $intentId, $refundId): void {
            $this->paymentIntentRepository->withLockedIntent($intentId, function ($record) use ($data, $intentId, $refundId): void {
                try {
                    if ($this->paymentIntentRepository->hasRecordedRefund($record, $refundId)) {
                        return;
                    }
                } catch (LocalizedException $e) {
                    throw new WebhookException(__('Invalid refund reconciliation data.'));
                }
                $order = $this->paymentIntentRepository->getOrder($intentId);
                if (!$order || !$order->getId()) {
                    throw new WebhookException(__('Can\'t find order'));
                }
                if ($order->getState() === Order::STATE_HOLDED && $order->canUnhold()) {
                    $order->unhold();
                }
                if (!$order->canCreditmemo()) {
                    throw new WebhookException(__('The refund cannot be applied until the order has a captured invoice.'));
                }
                $this->createCreditMemo($order, (float) $data->amount, (string) $data->currency, $intentId);
                $requestId = isset($data->request_id) && is_string($data->request_id) ? $data->request_id : '';
                $this->paymentIntentRepository->appendRefundToRecord($record, $refundId, $requestId);
            });
        });
    }

    /**
     * @param Order $order
     * @param float $refundAmount
     * @param string $refundCurrency
     * @param string $intentId
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function createCreditMemo(Order $order, float $refundAmount, string $refundCurrency, string $intentId): void
    {
        /** @var Order\Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        if (!$invoice || !$invoice->getId()) {
            throw new WebhookException(__('Refund invoice is not available yet.'));
        }

        $orderCurrency = (string) $order->getOrderCurrencyCode();
        $baseCurrency = (string) $order->getBaseCurrencyCode();
        $rate = (float) $order->getBaseToOrderRate();
        if ($refundCurrency === $orderCurrency) {
            $orderRefundAmount = AmountHelper::formatAmount($refundAmount, $orderCurrency);
            $baseAmount = $this->getBaseAmount(
                $refundAmount,
                $rate,
                (float) $order->getGrandTotal(),
                (float) $order->getBaseGrandTotal(),
                $orderCurrency,
                $baseCurrency
            );
        } elseif ($refundCurrency === $baseCurrency) {
            $baseAmount = AmountHelper::formatAmount($refundAmount, $baseCurrency);
            $orderRefundAmount = AmountHelper::formatAmount(
                ($rate ? $baseAmount * $rate : $baseAmount),
                $orderCurrency
            );
        } else {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntent = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $intentAmount = (float) $paymentIntent->getAmount();
            if ($refundCurrency !== $paymentIntent->getCurrency() || $intentAmount <= 0) {
                throw new WebhookException(__('Refund currency or payment intent amount is invalid.'));
            }
            if (AmountHelper::formatAmount($refundAmount, $refundCurrency)
                > AmountHelper::formatAmount($intentAmount, $refundCurrency)
            ) {
                throw new WebhookException(__('Refund amount exceeds the payment intent amount.'));
            }
            $baseGrandTotal = (float) $order->getBaseGrandTotal();
            $baseAmount = AmountHelper::formatAmount(
                $refundAmount / $intentAmount * $baseGrandTotal,
                $baseCurrency
            );
            $orderRefundAmount = AmountHelper::formatAmount(
                ($rate ? $baseAmount * $rate : $baseAmount),
                $orderCurrency
            );
        }

        $creditMemo = $this->creditmemoFactory->createByOrder($order);
        $creditMemo->setInvoice($invoice);
        $creditMemo->setGrandTotal($orderRefundAmount);
        $creditMemo->setBaseGrandTotal($baseAmount);
        $this->creditmemoService->refund($creditMemo, true);
        $this->orderRepository->save($order);
    }
}
