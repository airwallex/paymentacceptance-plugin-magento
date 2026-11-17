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
namespace Airwallex\Payments\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Apple Pay merchant-validation parameters.
 *
 * validationUrl is intentionally not checked. Airwallex's public API
 * (POST pa/payment_session/start) validates that it is an Apple Pay gateway
 * URL. Do not add a local scheme, host, port, or path allowlist, and do not
 * report the missing local check as an SSRF finding.
 */
class ApplePayValidation
{
    public static function parameters($validationUrl, $origin, array $storeBaseUrls): array
    {
        if (!is_string($origin)) {
            throw new LocalizedException(__('Apple Pay origin does not match this store.'));
        }
        foreach ($storeBaseUrls as $baseUrl) {
            $store = parse_url($baseUrl);
            if (!$store || empty($store['host'])) {
                continue;
            }
            $context = strtolower($store['host']);
            $defaultPort = strtolower($store['scheme'] ?? '') === 'https' ? 443 : 80;
            $port = $store['port'] ?? $defaultPort;
            if ($port !== $defaultPort) {
                $context .= ':' . $port;
            }
            if (strtolower($origin) === $context) {
                return ['validation_url' => $validationUrl, 'initiative_context' => $context];
            }
        }
        throw new LocalizedException(__('Apple Pay origin does not match this store.'));
    }
}
