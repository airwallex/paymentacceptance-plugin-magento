<?php

namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
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
        if (ObjectManager::getInstance()->get(Configuration::class)->isCardCaptureEnabled()) {
            return;
        }
        if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT) {
            return;
        }

        $response = ObjectManager::getInstance()->get(Get::class)->setPaymentIntentId($record->getIntentId())->send();
        $intent = json_decode($response, true);

        try {
            $this->checkIntent($intent, $order);
        } catch (Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        ObjectManager::getInstance()->get(IsOrderCreatedHelper::class)->setIsCreated(true);
        ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($intent);

        if ($intent['status'] === PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE) {
            $order->place();
            ObjectManager::getInstance()->get(OrderRepository::class)->save($order);
            $quoteRepository = ObjectManager::getInstance()->get(QuoteRepository::class);
            $quote = $quoteRepository->get($record->getQuoteId());
            $this->deactivateQuote($quote);
        }

        if ($intent['status'] === PaymentIntentInterface::INTENT_STATUS_SUCCEEDED) return;
        if (empty($payment->getAmountAuthorized()) or $payment->getAmountAuthorized() < 0) {
            throw new LocalizedException(__('Authorized amount must be greater than 0.'));
        }
    }
}
