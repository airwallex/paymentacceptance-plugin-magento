<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Sales\Model\OrderRepository;

abstract class AbstractWebhook
{
    /**
     * @var OrderRepository
     */
    protected OrderRepository $orderRepository;

    /**
     * @var PaymentIntentRepository
     */
    protected PaymentIntentRepository $paymentIntentRepository;

    /**
     * AbstractWebhook constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
    }

    /**
     * @param object $data
     *
     * @return void
     */
    abstract public function execute(object $data): void;
}
