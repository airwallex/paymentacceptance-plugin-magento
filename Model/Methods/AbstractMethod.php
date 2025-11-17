<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Capture;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\PaymentIntents;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
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
use Airwallex\Payments\Logger\Logger;
use Magento\Framework\App\CacheInterface;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\Sales\Model\Order;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund\Create as CreateRefund;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Refund as StructRefund;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
abstract class AbstractMethod extends Adapter
{
    use HelperTrait;

    public const CACHE_TAGS = ['airwallex'];
    public const PAYMENT_PREFIX = 'airwallex_payments_';
    public const ADDITIONAL_DATA = ['intent_id', 'intent_status'];

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var CreateRefund
     */
    private CreateRefund $createRefund;

    /**
     * @var Capture
     */
    protected Capture $capture;

    /**
     * @var PaymentIntentRepository
     */
    protected PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var Cancel
     */
    private Cancel $cancel;

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
     * @param Capture $capture
     * @param Cancel $cancel
     * @param CheckoutData $checkoutHelper
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param CancelHelper $cancelHelper
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param Logger $logger
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
        Capture                       $capture,
        Cancel                        $cancel,
        CheckoutData                  $checkoutHelper,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        CancelHelper                  $cancelHelper,
        PaymentIntentRepository       $paymentIntentRepository,
        Logger                        $logger,
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
            $logger
        );
        $this->paymentIntents = $paymentIntents;
        $this->logger = $logger;
        $this->createRefund = $createRefund;
        $this->capture = $capture;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cancel = $cancel;
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
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        if ($amount <= 0) return $this;

        $order = $payment->getOrder();
        /** @var Order $order */
        if ($order && $order->getId()) {
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

        if ($paymentIntent->isCaptured()) {
            return $this;
        }

        $this->cache->save(true, $this->captureCacheName($intentId), [], 3600);
        if (empty($order) || empty($order->getId())) {
            $captureAmount = $paymentIntent->getAmount();
        } else {
            if ($order->getBaseGrandTotal() <= 0) {
                throw new LocalizedException(__('The base grand total of the order must be greater than zero.'));
            }

            $captureAmount = $this->isAmountEqual($amount, $order->getBaseGrandTotal())
                ? $paymentIntent->getAmount()
                : $amount / $order->getBaseGrandTotal() * $paymentIntent->getAmount();
        }
        $decimal = PaymentIntents::CURRENCY_TO_DECIMAL[$paymentIntent->getCurrency()] ?? 2;
        $this->capture->setPaymentIntentId($intentId)->setInformation(round($captureAmount, $decimal))->send();
        return $this;
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws GuzzleException
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
        try {
            $this->cancel->setPaymentIntentId($intentId)->send();
        } catch (Exception $e) {
            if (strstr($e->getMessage(), 'CANCELLED')) {
                return $this;
            }
            /** @var Payment $payment */
            $this->logger->orderError($payment->getOrder(), 'cancel', $e->getMessage());
            throw new RuntimeException(__($e->getMessage()));
        }

        return $this;
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

        $intentId = $this->getIntentId($payment);

        $this->cache->save(true, $this->refundCacheName($intentId), [], 3600);
        try {
            /** @var StructPaymentIntent $paymentIntent */
            $paymentIntent = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $record = $this->paymentIntentRepository->getByIntentId($intentId);
            /** @var Creditmemo $credit */
            if ($credit->getOrderCurrencyCode() === $paymentIntent->getCurrency()) {
                $refundAmount = $credit->getGrandTotal();
            } else {
                $orderGrandTotal = $order->getGrandTotal();
                if ($this->isAmountEqual($credit->getGrandTotal(), $order->getGrandTotal()) && $credit->getOrderCurrencyCode() === $order->getOrderCurrencyCode()) {
                    $refundAmount = $paymentIntent->getAmount();
                } else {
                    $decimal = PaymentIntents::CURRENCY_TO_DECIMAL[$paymentIntent->getCurrency()] ?? 2;
                    if ($orderGrandTotal <= 0) {
                        throw new LocalizedException(__('The grand total of the order must be greater than zero.'));
                    }
                    $refundAmount = round($credit->getGrandTotal() / $orderGrandTotal * $paymentIntent->getAmount(), $decimal);
                }
            }
            if ($refundAmount <= 0) {
                throw new LocalizedException(__('The refund amount must be greater than zero.'));
            }
            $this->processBankTransfer($paymentIntent, $refundAmount);
            try {
                /** @var StructRefund $refundedObject */
                $refundedObject = $this->createRefund->setPaymentIntentId($intentId)->setAmount($refundAmount)->send();
            } catch (Exception $e) {
                $this->logError(__METHOD__ . ': ' . $e->getMessage());
                $this->cache->remove($this->refundCacheName($intentId));
                throw new LocalizedException(__('Something went wrong while trying to process the refund.'));
            }

            $detail = $record->getDetail();
            $detailArray = $detail ? json_decode($detail, true) : [];
            if (empty($detailArray['refund_ids'])) {
                $detailArray['refund_ids'] = [];
            }
            $detailArray['refund_ids'][] = $refundedObject->getId();
            $this->paymentIntentRepository->updateDetail($record, json_encode($detailArray));
        } catch (Exception $exception) {
            $this->logError(__METHOD__ . $exception->getMessage());
            throw new RuntimeException(__($exception->getMessage()));
        }
        return $this;
    }

    /**
     * @throws LocalizedException
     */
    private function processBankTransfer(StructPaymentIntent $paymentIntent, $refundAmount): void
    {
        if ($paymentIntent->getCurrency() === 'USD'
            && !empty($paymentIntent->getLatestPaymentAttempt()['payment_method']['type'])
            && $paymentIntent->getLatestPaymentAttempt()['payment_method']['type'] === 'bank_transfer') {
            if ($paymentIntent->getAmount() - $refundAmount >= 0.01) {
                throw new LocalizedException(__('Partial refunds are supported for USD, but only after additional bank account details are collected from the customer.
                    For more information, please refer to the following document: %1.', "https://www.airwallex.com/docs/payments__global__bank-transfer-beta#refunds"));
            }
        }
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws GuzzleException
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
        /** @var Order $order */
        $order = $payment->getOrder();
        $paymentIntent = $this->paymentIntentRepository->getByOrderIncrementIdAndStoreId($order->getIncrementId(), $order->getStoreId());
        if (!$paymentIntent || !$paymentIntent->getIntentId()) {
            return $this->getInfoInstance()->getAdditionalInformation('intent_id') ?: '';
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
            return MethodInterface::ACTION_AUTHORIZE_CAPTURE;
        }
        if ($paymentIntent->isAuthorized()) {
            return MethodInterface::ACTION_AUTHORIZE;
        }
        return '';
    }
}
