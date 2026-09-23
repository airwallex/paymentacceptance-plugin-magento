<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

use RuntimeException;

class CacheManager
{
    /**
     * @var CacheInterface|null
     */
    private static $cache = null;

    private function __construct()
    {
    }

    /**
     * @param CacheInterface $cache
     */
    public static function setInstance(CacheInterface $cache)
    {
        self::$cache = $cache;
    }

    /**
     * @return CacheInterface
     *
     * @throws RuntimeException
     */
    public static function getInstance()
    {
        if (self::$cache === null) {
            throw new RuntimeException('Cache not initialized');
        }
        return self::$cache;
    }
}
