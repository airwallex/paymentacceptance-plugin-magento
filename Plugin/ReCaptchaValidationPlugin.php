<?php

namespace Airwallex\Payments\Plugin;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\ReCaptchaValidation\Model\Validator;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;

/**
 * Enable ReCaptcha validation bypass for the 2nd request in the chain
 */
class ReCaptchaValidationPlugin
{
    public const WHITELIST_PATHS = [
        '/V1/airwallex/payments/guest-place-order' => true,
        '/V1/airwallex/payments/place-order' => true
    ];

    public const CACHE_PREFIX = 'RC_BYPASS';

    protected RestRequest $request;
    protected CacheInterface $cache;
    protected ValidationResultFactory $validationResultFactory;

    public function __construct(
        RestRequest             $request,
        CacheInterface          $cache,
        ValidationResultFactory $validationResultFactory
    )
    {
        $this->request = $request;
        $this->cache = $cache;
        $this->validationResultFactory = $validationResultFactory;
    }

    public function aroundIsValid(
        Validator                 $subject,
        callable                  $proceed,
        string                    $reCaptchaResponse,
        ValidationConfigInterface $validationConfig
    )
    {
        $uriPath = $this->request->getPathInfo();
        if (array_key_exists($uriPath, self::WHITELIST_PATHS)
            && $this->validateBypassReCaptcha()) {
            return $this->validationResultFactory->create(['errors' => []]);
        }

        return $proceed($reCaptchaResponse, $validationConfig);
    }

    protected function validateBypassReCaptcha(): bool
    {
        $requestData = $this->request->getRequestData();
        $intentId = $requestData['intent_id'] ?? false;
        if (!$intentId) {
            return false;
        }

        if ($this->cache->load($this->getCacheKey($intentId))) {
            $this->cache->remove($this->getCacheKey($intentId));
            return true;
        }

        return false;
    }

    public function getCacheKey(string $intentId): string
    {
        return implode('_', [self::CACHE_PREFIX, $intentId]);
    }
}
