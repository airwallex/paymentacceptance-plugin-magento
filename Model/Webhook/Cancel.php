<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\OrderRepository;

class Cancel extends AbstractWebhook
{
    public const WEBHOOK_NAME = 'payment_attempt.cancelled';

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
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     */
    public function execute(object $data): void
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->paymentIntentRepository->getOrder($paymentIntentId);
        if (!$order) {
            throw new WebhookException(__('Can\'t find order %s', $paymentIntentId));
        }

        if (!$order->canCancel()) {
            if ($order->isCanceled()) return;
            throw new WebhookException(__('Can\'t cancel order %s', $paymentIntentId));
        }

        $this->cancelHelper->setWebhookCanceling(true);
        $order->cancel();
        $order->addCommentToStatusHistory(__('Order cancelled through Airwallex.'));
        $this->orderRepository->save($order);
    }
}
