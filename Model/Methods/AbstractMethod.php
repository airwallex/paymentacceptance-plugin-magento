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
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Refund;
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
     * @var Refund
     */
    private Refund $refund;

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
     * @param Refund $refund
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
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param Configuration $configuration
     * @param RetrievePaymentIntent $retrievePaymentIntent
     */
    public function __construct(
        PaymentIntents                $paymentIntents,
        ManagerInterface              $eventManager,
        ValueHandlerPoolInterface     $valueHandlerPool,
        PaymentDataObjectFactory      $paymentDataObjectFactory,
        string                        $code,
        string                        $formBlockType,
        string                        $infoBlockType,
        Refund                        $refund,
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
        $this->refund = $refund;
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
        if ($order && $order->getId()) {
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
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }

        // capture in frontend element will run here too, but can not go inside
        if (!$paymentIntent->isAuthorized()) {
            return $this;
        }

        $this->cache->save(true, $this->captureCacheName($intentId), [], 3600);
        $this->capture->setPaymentIntentId($intentId)->setInformation($paymentIntent->getAmount())->send();
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
            if ($credit->getOrderCurrencyCode() === $paymentIntent->getCurrency()) {
                $refundAmount = $credit->getGrandTotal();
            } else {
                $orderGrandTotal = $order->getGrandTotal();
                if ($credit->getGrandTotal() === $order->getGrandTotal() && $credit->getOrderCurrencyCode() === $order->getOrderCurrencyCode()) {
                    $refundAmount = $paymentIntent->getAmount();
                } else {
                    $decimal = PaymentIntents::CURRENCY_TO_DECIMAL[$paymentIntent->getCurrency()] ?? 2;
                    $refundAmount = round($credit->getGrandTotal() / $orderGrandTotal * $paymentIntent->getAmount(), $decimal);
                }
            }
            $this->processBankTransfer($paymentIntent, $refundAmount);
            /** @var Creditmemo $credit */
            $res = $this->refund->setInformation($intentId, $refundAmount)->send();

            $detail = $record->getDetail();
            $detailArray = $detail ? json_decode($detail, true) : [];
            if (empty($detailArray['refund_ids'])) {
                $detailArray['refund_ids'] = [];
            }
            $detailArray['refund_ids'][] = $res->id;
            $this->paymentIntentRepository->updateDetail($record, json_encode($detailArray));
        } catch (GuzzleException $exception) {
            $this->logger->orderError($payment->getOrder(), 'refund', $exception->getMessage());
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
     * @throws GuzzleException
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
