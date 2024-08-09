<?php

namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Capture;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Refund;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\PaymentIntents;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use RuntimeException;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Airwallex\Payments\Logger\Logger;
use Magento\Framework\App\CacheInterface;

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
     * @var Confirm
     */
    protected Confirm $confirm;

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

    protected Get $intentGet;

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
     * @param Confirm $confirm
     * @param CheckoutData $checkoutHelper
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param CancelHelper $cancelHelper
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param Get $intentGet
     * @param Logger $logger
     * @param CacheInterface $cache
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
        Refund                        $refund,
        Capture                       $capture,
        Cancel                        $cancel,
        Confirm                       $confirm,
        CheckoutData                  $checkoutHelper,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        CancelHelper                  $cancelHelper,
        PaymentIntentRepository       $paymentIntentRepository,
        Get                           $intentGet,
        Logger                        $logger,
        CacheInterface                $cache,
        CommandPoolInterface          $commandPool = null,
        ValidatorPoolInterface        $validatorPool = null,
        CommandManagerInterface       $commandExecutor = null
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
        $this->confirm = $confirm;
        $this->checkoutHelper = $checkoutHelper;
        $this->intentGet = $intentGet;
        $this->cache = $cache;
    }

    /**
     * @param DataObject $data
     *
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(DataObject $data): self
    {
        $additionalData = $data->getData('additional_data');
        $info = $this->getInfoInstance();
        foreach (self::ADDITIONAL_DATA as $additionalDatum) {
            if (isset($additionalData[$additionalDatum])) {
                $info->setAdditionalInformation($additionalDatum, $additionalData[$additionalDatum]);
            }
        }

        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws AlreadyExistsException|LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(InfoInterface $payment, $amount): self
    {
        $this->setTransactionId($payment);
        return $this;
    }

    /**
     * @throws LocalizedException
     */
    protected function setTransactionId($payment)
    {
        $intentId = $this->getIntentId();
        if (empty($intentId)) {
            throw new LocalizedException(__('Something went wrong while trying to capture the payment.'));
        }
        /** @var Payment $payment */
        $payment->setTransactionId($intentId);
        $payment->setIsTransactionClosed(false);
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws LocalizedException
     */
    public function capture(InfoInterface $payment, $amount): self
    {
        $this->setTransactionId($payment);
        return $this;
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws Exception
     */
    public function cancel(InfoInterface $payment): self
    {
        if ($this->cancelHelper->isWebhookCanceling()) {
            return $this;
        }

        try {
            $this->cancel->setPaymentIntentId($this->getIntentId())->send();
        } catch (GuzzleException $exception) {
            /** @var Payment $payment */
            $this->logger->orderError($payment->getOrder(), 'cancel', $exception->getMessage());
            throw new RuntimeException(__($exception->getMessage()));
        }

        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param float $baseAmount
     *
     * @return $this
     * @throws Exception
     */
    public function refund(InfoInterface $payment, $baseAmount): self
    {
        /** @var Payment $payment */
        $credit = $payment->getCreditmemo();

        $intentId = $this->getIntentId();
        $this->cache->save(true, $this->refundCacheName($intentId), [], 3600);
        try {
            /** @var Creditmemo $credit */
            $res = $this->refund->setInformation($intentId, $credit->getGrandTotal())->send();

            $record = $this->paymentIntentRepository->getByIntentId($intentId);
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
//        sleep(4);
        return $this;
    }

    /**
     * @param InfoInterface $payment
     *
     * @return $this
     * @throws Exception
     */
    public function void(InfoInterface $payment): self
    {
        return $this->cancel($payment);
    }

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isAvailable($this->getPaymentMethodCode());
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    protected function getIntentId(): string
    {
        return $this->getInfoInstance()->getAdditionalInformation('intent_id');
    }

    /**
     * @return string
     */
    protected function getPaymentMethodCode(): string
    {
        return str_replace(self::PAYMENT_PREFIX, '', $this->getCode());
    }
}
