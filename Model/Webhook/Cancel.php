<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class Cancel extends AbstractWebhook
{
    public const WEBHOOK_NAME = 'payment_intent.cancelled';

    /**
     * @var CancelHelper
     */
    private CancelHelper $cancelHelper;

    /**
     * Cancel constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CancelHelper $cancelHelper
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CancelHelper $cancelHelper
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
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
        $order->addCommentToStatusHistory(__('Order cancelled through Airwallex.'));
        try {
            $this->orderRepository->save($order);
        } catch(Exception $e) {
            throw new Exception($e->getMessage() . " intent id: $paymentIntentId order id: {$order->getIncrementId()}");
        }
    }
}
