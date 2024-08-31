<?php

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
            throw new WebhookException(__("Can't find order $paymentIntentId"));
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
