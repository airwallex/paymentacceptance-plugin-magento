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

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\DeploymentConfig;
use RuntimeException;

class ReturnState
{
    /**
     * Signed-state scope for Magento orders
     */
    public const SCOPE_ORDER = 'order';

    /**
     * Signed-state scope for Magento quotes
     */
    public const SCOPE_QUOTE = 'quote';

    /**
     * Default TTL for in-flow state tokens, in seconds
     */
    public const ENTRY_TTL = 1800;

    /**
     * Default TTL for payment-return state tokens, in seconds
     */
    public const RETURN_TTL = 1800;

    /**
     * Payload version for signed state tokens
     */
    private const VERSION = 1;

    /**
     * Separator between payload and signature
     */
    private const SEPARATOR = '.';

    // Cover tokens issued before the return TTL was reduced to 30 minutes.
    private const LEGACY_RETURN_TTL = 86400;

    private const CONSUMED_TOKEN_PREFIX = 'awx_return_state_token_';
    private const CONSUMED_ENTITY_PREFIX = 'awx_return_state_entity_';

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    private CacheInterface $cache;

    /**
     * Constructor
     *
     * @param DeploymentConfig $deploymentConfig
     * @param CacheInterface $cache
     */
    public function __construct(DeploymentConfig $deploymentConfig, CacheInterface $cache)
    {
        $this->deploymentConfig = $deploymentConfig;
        $this->cache = $cache;
    }

    /**
     * Check whether Magento crypt key is configured for signing
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return (string) $this->deploymentConfig->get('crypt/key') !== '';
    }

    /**
     * Generate a signed return-state token for the given scope and entity id
     *
     * @param string $scope
     * @param int $id
     * @param int $ttl
     * @return string
     */
    public function generate(string $scope, int $id, int $ttl): string
    {
        $payload = [
            'v' => self::VERSION,
            'scope' => $scope,
            'id' => $id,
            'exp' => time() + max(1, $ttl),
            'nonce' => bin2hex(random_bytes(8)),
        ];

        $encoded = $this->base64UrlEncode((string) json_encode($payload));

        return $encoded . self::SEPARATOR . $this->sign($encoded);
    }

    /**
     * Validate a signed return-state token against the expected scope and entity id
     *
     * @param string $state
     * @param string $scope
     * @param int $id
     * @return bool
     */
    public function validate(string $state, string $scope, int $id): bool
    {
        $parts = explode(self::SEPARATOR, $state);
        if (count($parts) !== 2) {
            return false;
        }

        [$encoded, $signature] = $parts;
        if (!hash_equals($this->sign($encoded), $signature)) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($encoded), true);
        if (!is_array($payload)) {
            return false;
        }

        if (($payload['v'] ?? null) !== self::VERSION) {
            return false;
        }
        if (($payload['scope'] ?? '') !== $scope) {
            return false;
        }
        if ((int) ($payload['id'] ?? 0) !== $id) {
            return false;
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            return false;
        }
        if (empty($payload['nonce']) || !is_string($payload['nonce'])) {
            return false;
        }
        if ($this->isTokenConsumed($encoded) || $this->isEntityConsumed($scope, $id)) {
            return false;
        }

        return true;
    }

    public function consumeToken(string $state, int $ttl = self::RETURN_TTL): void
    {
        if ($state === '') {
            return;
        }
        $parts = explode(self::SEPARATOR, $state);
        if (count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return;
        }
        $payload = json_decode($this->base64UrlDecode($parts[0]), true);
        // validate() accepts the token through its expiry second.
        $ttl = max(1, $ttl, (int) ($payload['exp'] ?? 0) - time() + 1);
        $this->cache->save('1', $this->tokenCacheKey($parts[0]), [], $ttl);
    }

    public function consumeEntity(string $scope, int $id, int $ttl = self::RETURN_TTL): void
    {
        if ($id <= 0) {
            return;
        }
        $this->cache->save('1', $this->entityCacheKey($scope, $id), [], max(self::LEGACY_RETURN_TTL + 1, $ttl));
    }

    private function isTokenConsumed(string $encoded): bool
    {
        return (bool) $this->cache->load($this->tokenCacheKey($encoded));
    }

    private function isEntityConsumed(string $scope, int $id): bool
    {
        return (bool) $this->cache->load($this->entityCacheKey($scope, $id));
    }

    private function tokenCacheKey(string $encoded): string
    {
        return self::CONSUMED_TOKEN_PREFIX . hash('sha256', $encoded);
    }

    private function entityCacheKey(string $scope, int $id): string
    {
        return self::CONSUMED_ENTITY_PREFIX . $scope . '_' . $id;
    }

    /**
     * Sign encoded payload with Magento crypt key
     *
     * @param string $data
     * @return string
     */
    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->signingKey(), true));
    }

    /**
     * Resolve the HMAC signing key from Magento crypt configuration
     *
     * @return string
     */
    protected function signingKey(): string
    {
        $key = (string) $this->deploymentConfig->get('crypt/key');
        if ($key === '') {
            throw new RuntimeException('Magento crypt key is not configured.');
        }

        return $key;
    }

    /**
     * Base64url-encode binary data
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url-decode a signed-state payload
     *
     * @param string $data
     * @return string
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
