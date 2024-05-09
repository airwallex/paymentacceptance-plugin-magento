<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Client\Request\AvailablePaymentMethods;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var array
     */

    private CheckoutData $checkoutHelper;

    protected $methodsInExpress = [
        'googlepay',
        'applepay',
    ];

    /**
     * AvailablePaymentMethodsHelper constructor.
     *
     * @param AvailablePaymentMethods $availablePaymentMethod
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     * @param Configuration $configuration
     */
    public function __construct(
        AvailablePaymentMethods $availablePaymentMethod,
        CacheInterface $cache,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        Configuration $configuration,
        CheckoutData $checkoutHelper
    ) {
        $this->availablePaymentMethod = $availablePaymentMethod;
        $this->cache = $cache;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * @return bool
     */
    public function canInitialize(): bool
    {
        return !$this->configuration->getApiKey() === null &&
            !$this->configuration->getClientId() === null;
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
     */
    public function isAvailable(string $code): bool
    {
        if ($code === 'express') {
            return $this->canInitialize() && !!array_intersect($this->methodsInExpress, $this->getAllMethods());
        }
        return $this->canInitialize() && in_array($code, $this->getAllMethods(), true);
    }

    /**
     * @return array
     */
    private function getAllMethods(): array
    {
        $methods = $this->cache->load($this->getCacheName());

        if ($methods) {
            return $this->serializer->unserialize($methods);
        }

        try {
            $methods = $this->availablePaymentMethod->setCurrency($this->getCurrencyCode())->send();
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
