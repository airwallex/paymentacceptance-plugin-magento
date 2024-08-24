<?php

namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Client\Request\AvailablePaymentMethods;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Checkout\Helper\Data as CheckoutData;

class AvailablePaymentMethodsHelper
{
    private const CACHE_NAME = 'airwallex_payment_methods';
    private const CACHE_TIME = 60;
    /**
     * @var AvailablePaymentMethods
     */
    private AvailablePaymentMethods $availablePaymentMethod;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Configuration
     */
    public Configuration $configuration;

    /**
     * @var CheckoutData
     */
    private CheckoutData $checkoutHelper;

    protected array $methodsInExpress = [
        'googlepay',
        'applepay',
    ];

    /**
     * AvailablePaymentMethodsHelper constructor.
     *
     * @param AvailablePaymentMethods $availablePaymentMethod
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     */
    public function __construct(
        AvailablePaymentMethods $availablePaymentMethod,
        CacheInterface          $cache,
        SerializerInterface     $serializer,
        Configuration           $configuration,
        CheckoutData            $checkoutHelper
    )
    {
        $this->availablePaymentMethod = $availablePaymentMethod;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * @return bool
     */
    public function canInitialize(): bool
    {
        return $this->configuration->getApiKey() && $this->configuration->getClientId();
    }

    /**
     * @return bool
     */
    public function isMobileDetectInstalled(): bool
    {
        return class_exists('Detection\MobileDetect');
    }

    /**
     * @param string $code
     *
     * @return bool
     * @throws GuzzleException
     */
    public function isAvailable(string $code): bool
    {
        if ($code === 'airwallex_cc_vault') {
            $code = 'card';
        }
        if ($code === 'express') {
            return $this->canInitialize() && !!array_intersect($this->methodsInExpress, $this->getAllMethods());
        }
        return $this->canInitialize() && in_array($code, $this->getAllMethods(), true);
    }

    /**
     * @return mixed
     * @throws GuzzleException
     * @throws JsonException
     */
    private function fetch()
    {
        return $this->availablePaymentMethod->setCurrency($this->getCurrencyCode())->setResources()->setActive()->send();
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    private function getAllMethods(): array
    {
        $methods = $this->cache->load($this->getCacheName());

        if ($methods) {
            return $this->serializer->unserialize($methods);
        }

        try {
            $methods = $this->fetch();
        } catch (Exception $e) {
            $methods = [];
        }

        $this->cache->save(
            $this->serializer->serialize($methods),
            $this->getCacheName(),
            AbstractMethod::CACHE_TAGS,
            self::CACHE_TIME
        );
        return $methods;
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function getAllPaymentMethodTypes(): array
    {
        $methods = $this->cache->load($this->availablePaymentMethod->cacheName);

        if (!$methods) {
            try {
                $this->fetch();
            } catch (Exception $e) {
            }
            $methods = $this->cache->load($this->availablePaymentMethod->cacheName);
        }
        if (!$methods) {
            return [];
        }
        return json_decode($methods, true);
    }

    /**
     * @return string
     */
    private function getCacheName(): string
    {
        return self::CACHE_NAME . $this->getCurrencyCode();
    }

    /**
     * @return string
     */
    private function getCurrencyCode(): string
    {
        try {
            return $this->checkoutHelper->getQuote()->getQuoteCurrencyCode() ?: '';
        } catch (Exception $exception) {
            return '';
        }
    }
}
