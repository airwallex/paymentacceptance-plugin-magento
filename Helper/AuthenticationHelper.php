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

use Airwallex\Payments\Model\Client\Request\Authentication;
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
        CacheInterface $cache,
        ObjectManagerInterface $objectManager
    ) {
        $this->cache = $cache;
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     */
    public function getBearerToken(): string
    {
        $token = $this->cache->load(self::CACHE_NAME);

        if (empty($token)) {
            $authenticationData = $this->objectManager->create(Authentication::class)->send();
            $token = $authenticationData->token;
            $cacheLifetime = $this->getCacheLifetime($authenticationData->expires_at);
            $this->cache->save($token, self::CACHE_NAME, [], $cacheLifetime);
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
     * @throws \Exception
     */
    protected function getCacheLifetime(string $expiresAt): int
    {
        $expiresAtDate = new \DateTime($expiresAt);
        $currentDate = new \DateTime();
        $currentDate->setTimezone($expiresAtDate->getTimezone());
        return $expiresAtDate->getTimestamp() - $currentDate->getTimestamp();
    }
}
