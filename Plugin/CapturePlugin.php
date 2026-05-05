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
