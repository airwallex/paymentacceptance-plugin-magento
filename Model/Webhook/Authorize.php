<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\App\CacheInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\Payments\Helper\IntentHelper;

class Authorize extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_NAMES = [
        'payment_intent.requires_capture'
    ];

    private PaymentIntentRepository $paymentIntentRepository;
    private CartRepositoryInterface $quoteRepository;
    private RetrievePaymentIntent $retrievePaymentIntent;
    public CacheInterface $cache;
    private OrderManagementInterface $orderManagement;
    private OrderFactory $orderFactory;
    private OrderResourceInterface $orderResource;
    private IntentHelper $intentHelper;

    /**
     * Capture constructor.
     *
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param CacheInterface $cache
     * @param OrderManagementInterface $orderManagement
     * @param OrderFactory $orderFactory
     * @param OrderResourceInterface $orderResource
     * @param IntentHelper $intentHelper
     */
    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        CartRepositoryInterface  $quoteRepository,
        RetrievePaymentIntent    $retrievePaymentIntent,
        CacheInterface           $cache,
        OrderManagementInterface $orderManagement,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource,
        IntentHelper             $intentHelper
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->quoteRepository = $quoteRepository;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $this->cache = $cache;
        $this->orderManagement = $orderManagement;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->intentHelper = $intentHelper;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     * @throws GuzzleException
     */
    public function execute(object $data): void
    {
        $intentId = $data->payment_intent_id ?? $data->id;
        $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);

        $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
        $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
        $order = $this->getFreshOrder($paymentIntent->getOrderId());
        if (!empty($order) && !empty($order->getId())) {
            $this->changeOrderStatus($paymentIntentFromApi, $paymentIntent->getOrderId(), $quote, __METHOD__);
        } else {
            $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
        }
    }
}
