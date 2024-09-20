<?php

namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Client\Request\Authentication;
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
                $authenticationData = $this->objectManager->create(Authentication::class)->send();
                $token = $authenticationData->token;
                $cacheLifetime = $this->getCacheLifetime($authenticationData->expires_at);
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
