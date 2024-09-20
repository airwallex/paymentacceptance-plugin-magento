<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
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
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;

class Authorize extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_NAMES = [
        'payment_intent.requires_capture'
    ];

    private PaymentIntentRepository $paymentIntentRepository;
    private CartRepositoryInterface $quoteRepository;
    private Get $intentGet;
    public CacheInterface $cache;
    private OrderManagementInterface $orderManagement;
    private OrderFactory $orderFactory;
    private OrderResourceInterface $orderResource;
    private ErrorLog $errorLog;
    private IntentHelper $intentHelper;

    /**
     * Capture constructor.
     *
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param Get $intentGet
     * @param CacheInterface $cache
     * @param OrderManagementInterface $orderManagement
     * @param OrderFactory $orderFactory
     * @param OrderResourceInterface $orderResource
     * @param ErrorLog $errorLog
     * @param IntentHelper $intentHelper
     */
    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        CartRepositoryInterface  $quoteRepository,
        Get                      $intentGet,
        CacheInterface           $cache,
        OrderManagementInterface $orderManagement,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource,
        ErrorLog                 $errorLog,
        IntentHelper             $intentHelper
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->quoteRepository = $quoteRepository;
        $this->intentGet = $intentGet;
        $this->cache = $cache;
        $this->orderManagement = $orderManagement;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->errorLog = $errorLog;
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

        $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
        $intentResponse = json_decode($resp, true);
        $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
        $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote, 'webhook authorize');
    }
}
