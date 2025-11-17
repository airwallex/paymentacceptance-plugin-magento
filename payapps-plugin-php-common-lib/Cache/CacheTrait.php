<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

trait CacheTrait
{
    public function cacheRemember(string $key, callable $callback, int $ttl = 3600)
    {
        $cache = CacheManager::getInstance();
        $cachedValue = $cache->get($key);

        if (!empty ($cachedValue)) {
            return $cachedValue;
        }

        $value = $callback();
        $cache->set($key, $value, $ttl);
        return $value;
    }
}