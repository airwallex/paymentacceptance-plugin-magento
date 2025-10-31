<?php

namespace Airwallex\Payments\Plugin;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Operations\CaptureOperation;
use Magento\Sales\Model\OrderRepository;

class CapturePlugin
{
    use HelperTrait;

    /**
     * @param CaptureOperation $subject
     * @param OrderPaymentInterface $payment
     * @param $invoice
     * @throws LocalizedException
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     */
    public function beforeCapture(CaptureOperation $subject, OrderPaymentInterface $payment, $invoice)
    {
        if (empty($payment)) {
            return;
        }
        $order = $payment->getOrder();
        if (empty($order) || empty($order->getId())) {
            return;
        }
        $record = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getByOrderId($order->getId());
        if (empty($record)) {
            return;
        }
        if (ObjectManager::getInstance()->get(Configuration::class)->isAutoCapture('card')) {
            return;
        }
        if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT) {
            return;
        }

        /** @var StructPaymentIntent $paymentIntent */
        $paymentIntent = ObjectManager::getInstance()->get(RetrievePaymentIntent::class)->setPaymentIntentId($record->getIntentId())->send();

        try {
            $this->checkIntent($paymentIntent, $order);
        } catch (Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        ObjectManager::getInstance()->get(IsOrderCreatedHelper::class)->setIsCreated(true);
        ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($paymentIntent);

        if ($paymentIntent->isAuthorized()) {
            $order->place();
            ObjectManager::getInstance()->get(OrderRepository::class)->save($order);
            $quoteRepository = ObjectManager::getInstance()->get(QuoteRepository::class);
            $quote = $quoteRepository->get($record->getQuoteId());
            $this->deactivateQuote($quote);
        }

        if ($paymentIntent->isCaptured()) return;
        if (empty($payment->getAmountAuthorized()) or $payment->getAmountAuthorized() < 0) {
            throw new LocalizedException(__('Authorized amount must be greater than 0.'));
        }
    }
}
