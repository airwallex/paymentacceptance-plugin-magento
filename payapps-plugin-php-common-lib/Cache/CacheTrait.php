<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;

trait CacheTrait
{
    public function cacheRemember(string $key, callable $callback, int $ttl = 3600)
    {
        $key = $key . '_' . md5(Init::getInstance()->get('api_key'));
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