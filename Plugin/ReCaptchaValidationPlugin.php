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
