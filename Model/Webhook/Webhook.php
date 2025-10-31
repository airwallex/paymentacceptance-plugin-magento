<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\Configuration;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use stdClass;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;

class Webhook
{
    use HelperTrait;

    private const HASH_ALGORITHM = 'sha256';
    public const WEBHOOK_VERIFIED = 'verified';
    public const WEBHOOK_FAILED = 'failed';
    public const WEBHOOK_STATUS_NAME = 'airwallex/general/webhook';

    /**
     * @var Refund
     */
    private Refund $refund;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var Authorize
     */
    private Authorize $authorize;

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
     * @var OrderRepositoryInterface
     */
    public OrderRepositoryInterface $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    public CartRepositoryInterface $quoteRepository;

    /**
     * @var CartManagementInterface
     */
    public CartManagementInterface $cartManagement;

    /**
     * @var CacheInterface
     */
    public CacheInterface $cache;

    /**
     * @var LockManagerInterface
     */
    public LockManagerInterface $lockManager;

    /**
     * @var WriterInterface
     */
    public WriterInterface $configWriter;

    /**
     * @var ReinitableConfigInterface
     */
    public ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        Refund                    $refund,
        Configuration             $configuration,
        Capture                   $capture,
        Authorize                 $authorize,
        Expire                    $expire,
        Cancel                    $cancel,
        PaymentIntentRepository   $paymentIntentRepository,
        OrderRepositoryInterface  $orderRepository,
        CartRepositoryInterface   $quoteRepository,
        CartManagementInterface   $cartManagement,
        CacheInterface            $cache,
        LockManagerInterface      $lockManager,
        WriterInterface           $configWriter,
        ReinitableConfigInterface $reinitableConfig
    )
    {
        $this->refund = $refund;
        $this->configuration = $configuration;
        $this->capture = $capture;
        $this->expire = $expire;
        $this->authorize = $authorize;
        $this->cancel = $cancel;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->cartManagement = $cartManagement;
        $this->cache = $cache;
        $this->lockManager = $lockManager;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
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
        if (!$signature) {
            throw new WebhookException(__('signature is required'));
        }
        $data = $request->getHeader('x-timestamp') . $request->getContent();

        $mode = $this->configuration->getMode();
        $key = $this->configuration->getWebhookSecretKey();
        if (!$key) {
            throw new WebhookException(__("airwallex webhook $mode secret key is required"));
        }

        $status = hash_hmac(self::HASH_ALGORITHM, $data, $key) !== $signature ? self::WEBHOOK_FAILED : self::WEBHOOK_VERIFIED;

        try {
            $oldWebhook = $webhook = $this->configuration->getWebhook();
            $webhook["{$mode}_key"] = $key;
            $webhook["{$mode}_status"] = $status;

            if (empty($oldWebhook["{$mode}_key"])
                || empty($oldWebhook["{$mode}_status"])
                || $webhook["{$mode}_key"] !== $oldWebhook["{$mode}_key"]
                || $webhook["{$mode}_status"] !== $oldWebhook["{$mode}_status"]) {
                $this->configWriter->save(self::WEBHOOK_STATUS_NAME, json_encode($webhook));
                $this->reinitableConfig->reinit();
            }
        } catch (Exception $e) {
        }

        if ($status === self::WEBHOOK_FAILED) {
            throw new WebhookException(__('failed to verify the signature'));
        }
    }

    /**
     * @param string $type
     * @param stdClass $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
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

        if (in_array($type, Authorize::WEBHOOK_NAMES)) {
            $this->authorize->execute($data);
        }

        if (in_array($type, Capture::WEBHOOK_NAMES)) {
            $this->capture->execute($data);
        }

        if ($type === Cancel::WEBHOOK_NAME) {
            $this->cancel->execute($data);
        }
    }
}
