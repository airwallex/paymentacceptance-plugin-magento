<?php

namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment\Operations\CaptureOperation;
use Magento\Sales\Api\Data\InvoiceInterface;

class CapturePlugin
{
    /**
     * @throws LocalizedException
     */
    public function beforeCapture(CaptureOperation $subject, OrderPaymentInterface $payment, InvoiceInterface $invoice)
    {
        $orderId = $payment->getOrder()->getId();
        $repository = ObjectManager::getInstance()->get(PaymentIntentRepository::class);
        $record = $repository->getByOrderId($orderId);
        if (!$record) {
            return;
        }
        if (empty($payment->getAmountAuthorized()) or $payment->getAmountAuthorized() < 0) {
            throw new LocalizedException(__('Authorized amount must be greater than 0.'));
        }
    }
}
