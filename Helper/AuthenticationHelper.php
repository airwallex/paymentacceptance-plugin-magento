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
namespace Airwallex\Payments\Helper;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Authentication;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\AccessToken;
use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\ObjectManagerInterface;

class AuthenticationHelper
{
    private const CACHE_NAME = 'airwallex_token';

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * AuthenticationHelper constructor.
     *
     * @param CacheInterface $cache
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        CacheInterface         $cache,
        ObjectManagerInterface $objectManager
    )
    {
        $this->cache = $cache;
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    public function getBearerToken(): string
    {
        $token = $this->cache->load(self::CACHE_NAME);

        if (empty($token)) {
            try {
                /** @var AccessToken $accessToken */
                $accessToken = $this->objectManager->create(Authentication::class)->send();
                $token = $accessToken->getToken();
                $cacheLifetime = $this->getCacheLifetime($accessToken->getExpiresAt());
                $this->cache->save($token, self::CACHE_NAME, [], $cacheLifetime);
            } catch (Exception $e) {
                return '';
            }
        }

        return $token;
    }

    /**
     * Clear authentication token from cache.
     *
     * @return void
     */
    public function clearToken()
    {
        $this->cache->remove(self::CACHE_NAME);
    }

    /**
     * @param string $expiresAt
     * @return int
     * @throws Exception
     */
    protected function getCacheLifetime(string $expiresAt): int
    {
        $expiresAtDate = new DateTime($expiresAt);
        $currentDate = new DateTime();
        $currentDate->setTimezone($expiresAtDate->getTimezone());
        return $expiresAtDate->getTimestamp() - $currentDate->getTimestamp();
    }
}
