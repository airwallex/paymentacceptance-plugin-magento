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
namespace Airwallex\Payments\Model\Methods;

use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Cancel as CancelPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Capture as CapturePaymentIntent;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\PaymentOperationLock;
use Airwallex\Payments\Model\PaymentIntents;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;
use Exception;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use RuntimeException;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\Sales\Model\Order;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund\Create as CreateRefund;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
abstract class AbstractMethod extends Adapter
{
    use HelperTrait;

    public const PAYMENT_PREFIX = 'airwallex_payments_';
    public const ADDITIONAL_DATA = ['intent_id', 'intent_status'];

    /**
     * @var CreateRefund
     */
    private CreateRefund $createRefund;

    /**
     * @var CapturePaymentIntent
     */
    protected CapturePaymentIntent $capturePaymentIntent;

    /**
     * @var PaymentIntentRepository
     */
    protected PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var CancelPaymentIntent
     */
    private CancelPaymentIntent $cancelPaymentIntent;

    /**
     * @var CancelHelper
     */
    private CancelHelper $cancelHelper;

    /**
     * @var AvailablePaymentMethodsHelper
     */
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;

    /**
     * @var CheckoutData
     */
    protected CheckoutData $checkoutHelper;

    /**
     * @var PaymentIntents
     */
    protected PaymentIntents $paymentIntents;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    protected IsOrderCreatedHelper $isOrderCreatedHelper;
    protected IntentHelper $intentHelper;
    protected Configuration $configuration;
    protected RetrievePaymentIntent  $retrievePaymentIntent;

    /**
     * Payment constructor.
     *
     * @param PaymentIntents $paymentIntents
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CreateRefund $createRefund
     * @param CapturePaymentIntent $capturePaymentIntent
     * @param CancelPaymentIntent $cancelPaymentIntent
     * @param CheckoutData $checkoutHelper
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param CancelHelper $cancelHelper
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CacheInterface $cache
     * @param IntentHelper $intentHelper
     * @param IsOrderCreatedHelper $isOrderCreatedHelper
     * @param Configuration $configuration
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param CommonLibraryInit $commonLibraryInit
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     */
    public function __construct(
        PaymentIntents                $paymentIntents,
        ManagerInterface              $eventManager,
        ValueHandlerPoolInterface     $valueHandlerPool,
        PaymentDataObjectFactory      $paymentDataObjectFactory,
        string                        $code,
        string                        $formBlockType,
        string                        $infoBlockType,
        CreateRefund                  $createRefund,
        CapturePaymentIntent          $capturePaymentIntent,
        CancelPaymentIntent           $cancelPaymentIntent,
        CheckoutData                  $checkoutHelper,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        CancelHelper                  $cancelHelper,
        PaymentIntentRepository       $paymentIntentRepository,
        CacheInterface                $cache,
        IntentHelper                  $intentHelper,
        IsOrderCreatedHelper          $isOrderCreatedHelper,
        Configuration                 $configuration,
        RetrievePaymentIntent         $retrievePaymentIntent,
        CommonLibraryInit             $commonLibraryInit,
        ?CommandPoolInterface         $commandPool = null,
        ?ValidatorPoolInterface       $validatorPool = null,
        ?CommandManagerInterface      $commandExecutor = null
    )
    {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
        );
        $this->paymentIntents = $paymentIntents;
        $this->createRefund = $createRefund;
        $this->capturePaymentIntent = $capturePaymentIntent;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cancelPaymentIntent = $cancelPaymentIntent;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->cancelHelper = $cancelHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->cache = $cache;
        $this->isOrderCreatedHelper = $isOrderCreatedHelper;
        $this->intentHelper = $intentHelper;
        $this->configuration = $configuration;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $commonLibraryInit->exec();
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(InfoInterface $payment, $amount): self
    {
        if (!$this->configuration->isOrderBeforePayment()) {
            $paymentIntent = $this->intentHelper->getIntent();
            $order = $payment->getOrder();
            if ($order) {
                $this->assertIntentMatchesPayment($paymentIntent, $order);
            }
            /** @var Payment $payment */
            $this->setTransactionId($payment, $paymentIntent->getId());
        }
        return $this;
    }


    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return self
     * @throws InputException
     * @throws LocalizedException
     * @throws RequestException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        if ($amount <= 0) return $this;

        $order = $payment->getOrder();
        /** @var Order $order */
        if ($order && $order->getId()) {
            ObjectManager::getInstance()->get(PaymentOperationLock::class)
                ->assertOrderUnchanged($order);
            if ($order->getTotalPaid() > 0) {
                throw new LocalizedException(__('This order has already been captured and cannot be captured again.'));
            }
            /** @var Payment $payment */
            $intentId = $this->getIntentId($payment);
        } else {
            $paymentIntent = $this->intentHelper->getIntent();
            /** @var Payment $payment */
            $this->setTransactionId($payment, $paymentIntent->getId());
            $intentId = $paymentIntent->getId();
        }

        try {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntent = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }

        if ($order) {
            $this->assertIntentMatchesPayment($paymentIntent, $order);
        }

        if (empty($order) || empty($order->getId())) {
            $captureAmount = $paymentIntent->getAmount();
        } else {
            if ($order->getBaseGrandTotal() <= 0) {
                throw new LocalizedException(__('The base grand total of the order must be greater than zero.'));
            }
            $captureAmount = AmountHelper::amountEquals($amount, $order->getBaseGrandTotal(), $order->getBaseCurrencyCode())
                ? $paymentIntent->getAmount()
                : $amount / $order->getBaseGrandTotal() * $paymentIntent->getAmount();
        }
        $currency = $paymentIntent->getCurrency();
        if ($paymentIntent->isCaptured()) {
            if (!$this->isCaptureConfirmed($paymentIntent)) {
                throw new LocalizedException(__('This payment capture is not yet confirmed on Airwallex. Please wait for confirmation before invoicing.'));
            }
            if (!AmountHelper::amountEquals($captureAmount, $paymentIntent->getCapturedAmount(), $currency)) {
                throw new LocalizedException(__('The invoice amount does not match the confirmed Airwallex capture.'));
            }
            if ($payment instanceof Payment) {
                $payment->setIsTransactionPending(false);
            }
            return $this;
        }
        $capturedIntent = $this->capturePaymentIntent->setPaymentIntentId($intentId)
            ->setAmount(AmountHelper::formatAmount($captureAmount, $currency))->send();
        if ($capturedIntent instanceof StructPaymentIntent) {
            if ($this->isCaptureConfirmed($capturedIntent)) {
                if (!AmountHelper::amountEquals($captureAmount, $capturedIntent->getCapturedAmount(), $currency)) {
                    throw new LocalizedException(__('The invoice amount does not match the confirmed Airwallex capture.'));
                }
                if ($payment instanceof Payment) {
                    $payment->setIsTransactionPending(false);
                }
            } elseif ($payment instanceof Payment) {
                $payment->setIsTransactionPending(true);
            }
        }
        return $this;
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws InputException
     * @throws LocalizedException
     */
    public function cancel(InfoInterface $payment): self
    {
        if ($this->cancelHelper->isWebhookCanceling()) {
            return $this;
        }

        $intentId = $this->getIntentId($payment);
        $this->cache->save(true, $this->cancelCacheName($intentId), [], 3600);
        if ($this->cancelHelper->isIntentCanceled($intentId)) {
            return $this;
        }
        try {
            $this->cancelPaymentIntent->setPaymentIntentId($intentId)->send();
            $this->cancelHelper->markIntentCanceled($intentId);
        } catch (Exception $e) {
            if (strstr($e->getMessage(), 'CANCELLED') || $this->isIntentCancelledOnAirwallex($intentId)) {
                $this->cancelHelper->markIntentCanceled($intentId);
                return $this;
            }
            /** @var Payment $payment */
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw new RuntimeException(__($this->sanitizeUserErrorMessage($e, 'Unable to cancel the payment. Please try again.', true)));
        }

        return $this;
    }

    private function isIntentCancelledOnAirwallex(string $intentId): bool
    {
        if ($intentId === '') {
            return false;
        }
        try {
            $latest = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            return $latest instanceof StructPaymentIntent
                && $latest->getStatus() === StructPaymentIntent::STATUS_CANCELLED;
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws Exception
     */
    public function refund(InfoInterface $payment, $amount): self
    {
        /** @var Payment $payment */
        $credit = $payment->getCreditmemo();

        $order = $payment->getOrder();

        ObjectManager::getInstance()->get(PaymentOperationLock::class)
            ->assertHeld((int)$order->getId());
        $intentId = $this->getIntentId($payment);

        try {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntent = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            if ($credit->getOrderCurrencyCode() === $paymentIntent->getCurrency()) {
                $refundAmount = (float)$credit->getGrandTotal();
            } else {
                $orderGrandTotal = (float)$order->getGrandTotal();
                if ($this->isAmountEqual((float)$credit->getGrandTotal(), $orderGrandTotal, (string)$order->getOrderCurrencyCode()) && $credit->getOrderCurrencyCode() === $order->getOrderCurrencyCode()) {
                    $refundAmount = (float)$paymentIntent->getAmount();
                } else {
                    if ($orderGrandTotal <= 0) {
                        throw new LocalizedException(__('The grand total of the order must be greater than zero.'));
                    }
                    $refundAmount = $credit->getGrandTotal() / $orderGrandTotal * $paymentIntent->getAmount();
                }
            }
            $refundAmount = AmountHelper::formatAmount($refundAmount, $paymentIntent->getCurrency());
            if ($refundAmount <= 0) {
                throw new LocalizedException(__('The refund amount must be greater than zero.'));
            }
            $currency = (string)$paymentIntent->getCurrency();
            $this->processBankTransfer($paymentIntent, $refundAmount);
            // Commit the request id before the API call so a lost response retries the same id.
            $requestId = $this->paymentIntentRepository->reserveRefundRequest($intentId);
            $refundId = $this->createAirwallexRefund($intentId, $requestId, $refundAmount, $currency);
            $this->paymentIntentRepository->withLockedIntent(
                $intentId,
                function ($record) use ($refundId, $requestId): void {
                    if ($this->paymentIntentRepository->hasRecordedRefund($record, $refundId)) {
                        throw new LocalizedException(__('This Airwallex refund is already recorded on the order.'));
                    }
                    $this->paymentIntentRepository->appendRefundToRecord($record, $refundId, $requestId);
                }
            );
        } catch (Exception $exception) {
            $this->logError(__METHOD__ . ': ' . $exception);
            throw new RuntimeException(__($this->sanitizeUserErrorMessage($exception, 'Something went wrong while trying to process the refund.')), 0, $exception);
        }
        return $this;
    }

    private function createAirwallexRefund(string $intentId, string $requestId, float $amount, string $currency): string
    {
        try {
            $refundedObject = $this->createRefund
                ->setPaymentIntentId($intentId)
                ->setAmount($amount)
                ->setCurrency($currency)
                ->setRequestId($requestId)
                ->send();
        } catch (RequestException $exception) {
            $this->paymentIntentRepository->clearRefundRequest($intentId, $requestId);
            throw new LocalizedException(__('Something went wrong while trying to process the refund.'), $exception);
        } catch (Exception $exception) {
            throw new LocalizedException(__('The refund result is uncertain. Retry it before sending another refund.'), $exception);
        }
        $refundId = $refundedObject->getId();
        if ($refundId === '') {
            throw new LocalizedException(__('The refund result is uncertain. Retry it before sending another refund.'));
        }
        return $refundId;
    }

    /**
     * @throws LocalizedException
     */
    private function processBankTransfer(StructPaymentIntent $paymentIntent, $refundAmount): void
    {
        if ($paymentIntent->getCurrency() === 'USD'
            && !empty($paymentIntent->getLatestPaymentAttempt()['payment_method']['type'])
            && $paymentIntent->getLatestPaymentAttempt()['payment_method']['type'] === 'bank_transfer') {
            if (AmountHelper::formatAmount($refundAmount, $paymentIntent->getCurrency())
                < AmountHelper::formatAmount($paymentIntent->getAmount(), $paymentIntent->getCurrency())
            ) {
                throw new LocalizedException(__('Partial refunds are supported for USD, but only after additional bank account details are collected from the customer.
                    For more information, please refer to the following document: %1.', "https://www.airwallex.com/docs/payments__global__bank-transfer-beta#refunds"));
            }
        }
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws InputException
     * @throws LocalizedException
     */
    public function void(InfoInterface $payment): self
    {
        return $this->cancel($payment);
    }

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     * @throws Exception
     */
    public function isAvailable(?CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isAvailable($this->getPaymentMethodCode($this->getCode()));
    }

    /**
     * @param $payment
     * @return string
     * @throws InputException
     * @throws LocalizedException
     */
    protected function getIntentId($payment): string
    {
        $intentId = $payment->getAdditionalInformation('intent_id')
            ?: $this->getInfoInstance()->getAdditionalInformation('intent_id')
            ?: $payment->getLastTransId();
        if (is_string($intentId) && strpos($intentId, 'int_') === 0) {
            return (string) preg_replace('/-(?:capture|refund|void)$/', '', $intentId);
        }
        /** @var Order $order */
        $order = $payment->getOrder();
        $paymentIntent = $this->paymentIntentRepository->getByOrderIncrementIdAndStoreId($order->getIncrementId(), $order->getStoreId());
        if (!$paymentIntent || !$paymentIntent->getIntentId()) {
            return is_string($intentId) ? $intentId : '';
        }
        return $paymentIntent->getIntentId();
    }

    public function getConfigPaymentAction(): string
    {
        $paymentIntent = $this->intentHelper->getIntent();
        if (empty($paymentIntent->getStatus())) {
            return '';
        }
        if ($paymentIntent->isCaptured()) {
            // Magento's authorize_capture action creates a full-order invoice inside place().
            // Partial or unconfirmed captures are invoiced explicitly from the confirmed captured amount.
            return $this->isFullCaptureConfirmed($paymentIntent) ? MethodInterface::ACTION_AUTHORIZE_CAPTURE : '';
        }
        if ($paymentIntent->isAuthorized()) {
            return MethodInterface::ACTION_AUTHORIZE;
        }
        return '';
    }
}
