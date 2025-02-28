<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\App\CacheInterface;
use Magento\Sales\Model\OrderRepository;

class Expire extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_NAMES = ['payment_attempt.expired'];

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
     */
    public function execute(object $data): void
    {
    }
}
