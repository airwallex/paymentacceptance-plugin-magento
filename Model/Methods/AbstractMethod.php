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
namespace Airwallex\Payments\Model\Methods;

use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Capture;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Refund;
use Airwallex\Payments\Model\PaymentIntentRepository;
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
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
abstract class AbstractMethod extends Adapter
{
    public const CACHE_TAGS = ['airwallex'];
    public const PAYMENT_PREFIX = 'airwallex_payments_';
    public const ADDITIONAL_DATA = ['intent_id', 'intent_status'];

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

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
    private PaymentIntentRepository $paymentIntentRepository;

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
    private AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;

    /**
     * @var Confirm
     */
    protected Confirm $confirm;

    /**
     * @var CheckoutData
     */
    protected CheckoutData $checkoutHelper;

    /**
     * Payment constructor.
     *
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
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        Refund $refund,
        Capture $capture,
        Cancel $cancel,
        Confirm $confirm,
        CheckoutData $checkoutHelper,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        CancelHelper $cancelHelper,
        PaymentIntentRepository $paymentIntentRepository,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        LoggerInterface $logger = null
    ) {
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

        $this->logger = $logger;
        $this->refund = $refund;
        $this->capture = $capture;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cancel = $cancel;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->cancelHelper = $cancelHelper;
        $this->confirm = $confirm;
        $this->checkoutHelper = $checkoutHelper;
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
        $intentId = $this->getIntentId();

        $payment->setTransactionId($intentId);
        $payment->setIsTransactionClosed(false);

        $this->paymentIntentRepository->save(
            $payment->getOrder()->getIncrementId(),
            $intentId
        );

        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(InfoInterface $payment, $amount): self
    {
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

        $paymentTransactionId = str_replace(['-void', '-cancel'], '', $payment->getTransactionId());

        try {
            $this->cancel->setPaymentIntentId($paymentTransactionId)->send();
        } catch (GuzzleException $exception) {
            $this->logger->orderError($payment->getOrder(), 'cancel', $exception->getMessage());
            throw new RuntimeException(__($exception->getMessage()));
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
        $paymentTransactionId = str_replace('-refund', '', $payment->getTransactionId());
        $paymentTransactionId = str_replace('-capture', '', $paymentTransactionId);
        try {
            $this->refund
                ->setInformation($paymentTransactionId, $amount)
                ->send();
        } catch (GuzzleException $exception) {
            $this->logger->orderError($payment->getOrder(), 'refund', $exception->getMessage());
            throw new RuntimeException(__($exception->getMessage()));
        }

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

    /**
     * @return string
     * @throws LocalizedException
     */
    protected function getIntentStatus(): string
    {
        return $this->getInfoInstance()->getAdditionalInformation('intent_status');
    }
}
