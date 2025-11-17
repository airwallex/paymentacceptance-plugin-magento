<?php

namespace Airwallex\Payments\Helper;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentMethodType\GetList as GetPaymentMethodTypeList;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Checkout\Helper\Data as CheckoutData;

class AvailablePaymentMethodsHelper
{
    use HelperTrait;

    private const CACHE_NAME = 'airwallex_payment_methods';
    private const CACHE_TIME = 60;
    /**
     * @var GetPaymentMethodTypeList
     */
    private GetPaymentMethodTypeList $getPaymentMethodTypeList;

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
     * @param GetPaymentMethodTypeList $getPaymentMethodTypeList
     * @param CacheInterface $cache
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     */
    public function __construct(
        GetPaymentMethodTypeList $getPaymentMethodTypeList,
        CacheInterface           $cache,
        Configuration            $configuration,
        CheckoutData             $checkoutHelper
    )
    {
        $this->getPaymentMethodTypeList = $getPaymentMethodTypeList;
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
     * @throws Exception
     */
    public function isAvailable(string $code): bool
    {
        $code = $this->trimPaymentMethodCode($code);
        if ($code === 'vault') {
            $code = 'card';
        }
        if ($code === 'express') {
            return $this->canInitialize() && !!array_intersect($this->methodsInExpress, $this->getAllPaymentMethodTypeNames());
        }
        if ($code === 'bank_transfer' && !$this->configuration->isMethodActive('bank_transfer')) {
            return false;
        }
        return $this->canInitialize() && in_array($code, $this->getAllPaymentMethodTypeNames(), true);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getAllPaymentMethodTypeNames(): array
    {
        $paymentMethodTypes = $this->getAllPaymentMethodTypes();
        $methods = [];
        /** @var PaymentMethodType $paymentMethodType */
        foreach ($paymentMethodTypes as $paymentMethodType) {
            if (!empty($paymentMethodType->getName())) $methods[] = $paymentMethodType->getName();
        }
        return $methods;
    }

    public function getCardLogos(): array
    {
        $cacheName = 'awxCardLogos';
        $cardLogos = $this->cache->load($cacheName);
        if (!empty($cardLogos)) {
            $logos = json_decode($cardLogos, true);
            if (!empty($logos)) {
                return $logos;
            }
        }
        $logos = [];
        try {
            $paymentMethodTypes = $this->getAllPaymentMethodTypes();
            /** @var PaymentMethodType $paymentMethodType */
            foreach ($paymentMethodTypes as $paymentMethodType) {
                if ($paymentMethodType->getName() === 'card') {
                    $cardSchemes = $paymentMethodType->getCardSchemes();
                    foreach ($cardSchemes as $cardScheme) {
                        $logos[$cardScheme['name']] = $cardScheme['resources']['logos']['png'] ?? '';
                    }
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
        }
        $this->cache->save(json_encode($logos), $cacheName, [], 3600);
        return $logos;
    }

    /**
     * @throws Exception
     */
    public function getAllPaymentMethodTypes(): array
    {
        try {
            return $this->getPaymentMethodTypeList
                ->setActive(true)
                ->setCacheTime(60)
                ->setTransactionCurrency('')
                ->setIncludeResources(true)
                ->setTransactionMode(PaymentMethodType::PAYMENT_METHOD_TYPE_ONE_OFF)
                ->get();
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            return [];
        }
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
