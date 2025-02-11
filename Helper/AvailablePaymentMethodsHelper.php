<?php

namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Client\Request\AvailablePaymentMethods;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
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
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     */
    public function __construct(
        AvailablePaymentMethods $availablePaymentMethod,
        CacheInterface          $cache,
        Configuration           $configuration,
        CheckoutData            $checkoutHelper
    )
    {
        $this->availablePaymentMethod = $availablePaymentMethod;
        $this->cache = $cache;
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
     * @param string $code
     *
     * @return bool
     * @throws JsonException|GuzzleException
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
    private function getItems()
    {
        $cacheName = $this->getCacheName();
        $items = $this->cache->load($cacheName);
        if ($items) return json_decode($items, true);

        $resp = $this->getLatestItems();
        $this->cache->save(json_encode($resp), $cacheName, AbstractMethod::CACHE_TAGS, self::CACHE_TIME);
        return $resp;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getLatestItems($useQuote = true)
    {
        $request = $this->availablePaymentMethod
            ->setResources()
            ->setActive()
            ->setTransactionMode(AvailablePaymentMethods::TRANSACTION_MODE);
        if ($useQuote) {
            $request = $request->setCurrency($this->getCurrencyCode());
        } else {
            $request = $request->removeCurrency();
        }
        try {
            return $request->send();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array
     * @throws JsonException|GuzzleException
     */
    private function getAllMethods(): array
    {
        $items = $this->getItems();
        $methods = [];
        foreach ($items as $item) {
            if (!empty($item['name'])) $methods[] = $item['name'];
        }
        return $methods;
    }

    /**
     * @return array
     * @throws JsonException|GuzzleException
     */
    public function getAllPaymentMethodTypes(): array
    {
        return $this->getItems();
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
