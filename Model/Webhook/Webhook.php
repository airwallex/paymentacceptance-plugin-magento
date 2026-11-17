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
    public const SIGNATURE_TOLERANCE_SECONDS = 300;
    public const WEBHOOK_VERIFIED = 'verified';
    public const WEBHOOK_FAILED = 'failed';
    public const WEBHOOK_STATUS_NAME = 'airwallex/general/webhook';

    /**
     * Check that a webhook or settings-callback x-timestamp header (seconds or
     * milliseconds) is within the allowed replay window.
     *
     * @param string $timestamp
     * @param int|null $nowSeconds
     * @return bool
     */
    public static function isTimestampFresh(string $timestamp, ?int $nowSeconds = null): bool
    {
        $timestamp = trim($timestamp);
        if (!preg_match('/^\d{10}(\d{3})?$/', $timestamp)) {
            return false;
        }

        $timestampSeconds = strlen($timestamp) === 13
            ? (int) floor(((float) $timestamp) / 1000)
            : (int) $timestamp;
        $now = $nowSeconds ?? time();

        return abs($now - $timestampSeconds) <= self::SIGNATURE_TOLERANCE_SECONDS;
    }

    /**
     * Hash a webhook secret key so the plaintext secret is never persisted in
     * the unencrypted airwallex/general/webhook status config row.
     *
     * @param string $secretKey
     * @return string
     */
    public static function hashSecretKey(string $secretKey): string
    {
        return hash(self::HASH_ALGORITHM, $secretKey);
    }

    /**
     * Constant-time HMAC-SHA256 verification over timestamp + raw body.
     *
     * @param string $timestamp
     * @param string $rawBody
     * @param string $secretKey
     * @param string $signature
     * @return bool
     */
    public static function verifySignature(
        string $timestamp,
        string $rawBody,
        string $secretKey,
        string $signature
    ): bool {
        if ($secretKey === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac(self::HASH_ALGORITHM, $timestamp . $rawBody, $secretKey);

        return hash_equals($expected, $signature);
    }

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
        $signature = (string) $request->getHeader('x-signature');
        if ($signature === '') {
            throw new WebhookException(__('signature is required'));
        }
        $timestamp = (string) $request->getHeader('x-timestamp');
        if (!self::isTimestampFresh($timestamp)) {
            throw new WebhookException(__('webhook timestamp is invalid or expired'));
        }

        $mode = $this->configuration->getMode();
        $key = (string) $this->configuration->getWebhookSecretKey();
        if (trim($key) === '') {
            throw new WebhookException(__("airwallex webhook $mode secret key is required"));
        }

        // Unauthenticated requests must not change merchant configuration or invalidate its cache.
        if (!self::verifySignature($timestamp, (string) $request->getContent(), $key, $signature)) {
            throw new WebhookException(__('failed to verify the signature'));
        }
        $keyHash = self::hashSecretKey($key);

        try {
            $oldWebhook = $webhook = $this->configuration->getWebhook();
            $webhook["{$mode}_key"] = $keyHash;
            $webhook["{$mode}_status"] = self::WEBHOOK_VERIFIED;

            if (empty($oldWebhook["{$mode}_key"])
                || empty($oldWebhook["{$mode}_status"])
                || $webhook["{$mode}_key"] !== $oldWebhook["{$mode}_key"]
                || $webhook["{$mode}_status"] !== $oldWebhook["{$mode}_status"]) {
                $this->configWriter->save(self::WEBHOOK_STATUS_NAME, json_encode($webhook));
                $this->reinitableConfig->reinit();
            }
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
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
        $paymentIntentId = (string) ($data->payment_intent_id ?? $data->id ?? '');
        if ($paymentIntentId === '') {
            return;
        }
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
