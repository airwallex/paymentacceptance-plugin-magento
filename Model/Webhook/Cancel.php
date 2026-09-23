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
namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class Cancel extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_NAME = 'payment_intent.cancelled';

    /**
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;

    /**
     * @var PaymentIntentRepository
     */
    private PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var CancelHelper
     */
    private CancelHelper $cancelHelper;

    /**
     * Cancel constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CacheInterface $cache
     * @param CancelHelper $cancelHelper
     */
    public function __construct(
        OrderRepository         $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CacheInterface          $cache,
        CancelHelper            $cancelHelper
    )
    {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cache = $cache;
        $this->cancelHelper = $cancelHelper;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     * @throws Exception
     */
    public function execute(object $data): void
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;

        if ($this->cache->load($this->cancelCacheName($paymentIntentId))) {
            $this->cache->remove($this->cancelCacheName($paymentIntentId));
            return;
        }

        /** @var Order $order */
        $order = $this->paymentIntentRepository->getOrder($paymentIntentId);
        if (!$order) {
            return;
        }

        if (!$order->canCancel()) {
            if ($order->isCanceled()) return;
            throw new WebhookException(__("Can't cancel order $paymentIntentId"));
        }

        $this->cancelHelper->setWebhookCanceling(true);
        $order->cancel();
        $reason =  $data->cancellation_reason ?? '';
        if ($reason) { $reason = ' Reason: ' . $reason . '.'; }
        $order->addCommentToStatusHistory(__('Order canceled through Airwallex.' . $reason));
        try {
            $this->orderRepository->save($order);
        } catch (Exception $e) {
            throw new Exception($e->getMessage() . " intent id: $paymentIntentId");
        }
    }
}
