<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\Configuration;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use stdClass;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartManagementInterface;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Lock\LockManagerInterface;

class Webhook
{
    use HelperTrait;

    private const HASH_ALGORITHM = 'sha256';

    public const AUTHORIZED_WEBHOOK_NAMES = ['payment_intent.requires_capture'];

    /**
     * @var Refund
     */
    private Refund $refund;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var Capture
     */
    private Capture $capture;

    /**
     * @var Expire
     */
    private Expire $expire;

    /**
     * @var Cancel
     */
    private Cancel $cancel;

    /**
     * @var PaymentIntentRepository
     */
    protected PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var Get
     */
    public Get $intentGet;

    /**
     * @var OrderRepositoryInterface
     */
    public OrderRepositoryInterface $orderRepository;

    /**
     * @var Order
     */
    public OrderInterface $order;

    /**
     * @var CartRepositoryInterface
     */
    public CartRepositoryInterface $quoteRepository;

    /**
     * @var CartManagementInterface
     */
    public CartManagementInterface $cartManagement;

    /**
     * @var ErrorLog
     */
    public ErrorLog $errorLog;

    /**
     * @var CacheInterface
     */
    public CacheInterface $cache;

    /**
     * @var LockManagerInterface
     */
    public LockManagerInterface $lockManager;

    public function __construct(
        Refund $refund,
        Configuration $configuration,
        Capture $capture,
        Expire $expire,
        Cancel $cancel,
        PaymentIntentRepository $paymentIntentRepository,
        Get $intentGet,
        OrderRepositoryInterface $orderRepository,
        OrderInterface $order,
        CartRepositoryInterface $quoteRepository,
        CartManagementInterface $cartManagement,
        ErrorLog $errorLog,
        CacheInterface $cache,
        LockManagerInterface $lockManager
    )
    {
        $this->refund = $refund;
        $this->configuration = $configuration;
        $this->capture = $capture;
        $this->expire = $expire;
        $this->cancel = $cancel;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->intentGet = $intentGet;
        $this->orderRepository = $orderRepository;
        $this->order = $order;
        $this->quoteRepository = $quoteRepository;
        $this->cartManagement = $cartManagement;
        $this->errorLog = $errorLog;
        $this->cache = $cache;
        $this->lockManager = $lockManager;
    }

    /**
     * @param Http $request
     *
     * @return void
     * @throws WebhookException
     */
    public function checkChecksum(Http $request): void
    {
        $signature = $request->getHeader('x-signature');
        $data = $request->getHeader('x-timestamp') . $request->getContent();

        if (hash_hmac(self::HASH_ALGORITHM, $data, $this->configuration->getWebhookSecretKey()) !== $signature) {
            throw new WebhookException(__('failed to verify the signature'));
        }
    }

    /**
     * @param string $type
     * @param stdClass $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     * @throws JsonException
     */
    public function dispatch(string $type, stdClass $data): void
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;
        try {
            $this->paymentIntentRepository->getByIntentId($paymentIntentId);
        } catch (NoSuchEntityException $e) {
            return;
        }

        if ($type === Refund::WEBHOOK_SUCCESS_NAME) {
            $this->refund->execute($data);
        }

        if (in_array($type, Expire::WEBHOOK_NAMES)) {
            $this->expire->execute($data);
        }

        if (in_array($type, self::AUTHORIZED_WEBHOOK_NAMES)) {
            $this->placeOrder($data);
        }

        if (in_array($type, Capture::WEBHOOK_NAMES)) {
            $this->placeOrder($data);
            $this->capture->execute($data);
        }

        if ($type === Cancel::WEBHOOK_NAME) {
            $this->cancel->execute($data);
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     */
    public function placeOrder($data)
    {
        $paymentIntentId = $data->payment_intent_id ?? $data->id;
        $paymentIntent = $this->paymentIntentRepository->getByIntentId($paymentIntentId);

        $order = $this->order->loadByIncrementIdAndStoreId($paymentIntent->getOrderIncrementId(), $paymentIntent->getStoreId());
        if (!$order || !$order->getEntityId()) {
            sleep(7);
            $order = $this->order->loadByIncrementIdAndStoreId($paymentIntent->getOrderIncrementId(), $paymentIntent->getStoreId());
            if (!$order || !$order->getEntityId()) {
                /** @var Quote $quote */
                $quote = $this->quoteRepository->get($paymentIntent->getQuoteId());
                $this->checkIntentWithQuote(
                    $data->status,
                    $data->currency,
                    $quote->getQuoteCurrencyCode(),
                    $data->merchant_order_id,
                    $quote->getReservedOrderId(),
                    floatval($data->amount),
                    $quote->getGrandTotal(),
                );

                $this->placeOrderByQuoteId($quote->getId(), 'webhook');
            }
        }
    }
}
