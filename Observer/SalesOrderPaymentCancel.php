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
namespace Airwallex\Payments\Observer;

use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Airwallex\Payments\Model\Methods\Vault;
use Magento\Quote\Api\CartRepositoryInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Cancel as CancelPaymentIntent;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;

class SalesOrderPaymentCancel implements ObserverInterface
{
    use HelperTrait;

    private CartRepositoryInterface $quoteRepository;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected CancelHelper $cancelHelper;
    protected CancelPaymentIntent $cancelPaymentIntent;
    protected RetrievePaymentIntent $retrievePaymentIntent;

    public function __construct(
        PaymentIntentRepository $paymentIntentRepository,
        CancelHelper            $cancelHelper,
        CancelPaymentIntent     $cancelPaymentIntent,
        RetrievePaymentIntent   $retrievePaymentIntent,
        CommonLibraryInit       $commonLibraryInit,
        CartRepositoryInterface $quoteRepository
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cancelHelper = $cancelHelper;
        $this->cancelPaymentIntent = $cancelPaymentIntent;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $this->quoteRepository = $quoteRepository;
        $commonLibraryInit->exec();
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $this->cancel($observer->getPayment()->getOrder());
    }

    public function cancel(Order $order): bool
    {
        $payment = $order->getPayment();
        $method = $payment ? (string)$payment->getMethod() : '';
        if ($this->cancelHelper->isWebhookCanceling()
            || (strpos($method, AbstractMethod::PAYMENT_PREFIX) !== 0 && $method !== Vault::CODE)
        ) {
            return true;
        }
        $paymentIntentFromDB = $this->paymentIntentRepository->getByOrderId($order->getId());
        if (!$paymentIntentFromDB) {
            return true;
        }
        $boundIntentId = $payment->getAdditionalInformation('intent_id') ?: $payment->getLastTransId();
        $intentId = (is_string($boundIntentId) && strpos($boundIntentId, 'int_') === 0)
            ? (string) preg_replace('/-(?:capture|refund|void)$/', '', $boundIntentId)
            : $paymentIntentFromDB->getIntentId();
        if ($this->cancelHelper->isIntentCanceled($intentId)) {
            return true;
        }
        /** @var StructPaymentIntent $paymentIntentFromApi */
        $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
        if ($paymentIntentFromApi->getStatus() === StructPaymentIntent::STATUS_CANCELLED) {
            $this->cancelHelper->markIntentCanceled($intentId);
            return true;
        }
        if ($this->shouldReconcile($order, $paymentIntentFromApi)) {
            return $this->reconcilePayment($order, $paymentIntentFromApi);
        }
        try {
            $this->cancelPaymentIntent->setPaymentIntentId($intentId)->send();
        } catch (Exception $e) {
            RemoteLog::error(__METHOD__ . $e->getMessage(), 'onApiRequestError');
            try {
                $latest = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            } catch (Exception $retrieveException) {
                RemoteLog::error(__METHOD__ . $retrieveException->getMessage(), 'onApiRequestError');
                throw new LocalizedException(__('Airwallex could not cancel this payment.'));
            }
            if ($latest->getStatus() === StructPaymentIntent::STATUS_CANCELLED) {
                $this->cancelHelper->markIntentCanceled($intentId);
                return true;
            }
            if ($this->shouldReconcile($order, $latest)) {
                return $this->reconcilePayment($order, $latest);
            }
            throw new LocalizedException(__('Airwallex could not cancel this payment.'));
        }
        $this->cancelHelper->markIntentCanceled($intentId);
        return true;
    }

    private function shouldReconcile(Order $order, StructPaymentIntent $intent): bool
    {
        if ($intent->isCaptured()) {
            return true;
        }
        $payment = $order->getPayment();
        return $intent->isAuthorized() && !($payment && (float) $payment->getAmountAuthorized() > 0);
    }

    private function reconcilePayment(Order $order, StructPaymentIntent $intent): bool
    {
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $this->changeOrderStatus($intent, (int)$order->getId(), $quote, __METHOD__);
        $updated = $this->getFreshOrder((int)$order->getId());
        $order->setData($updated->getData());
        $order->setPayment($updated->getPayment());
        $order->setItems($updated->getItems());
        $order->setActionFlag(Order::ACTION_FLAG_CANCEL, false);
        return false;
    }
}
