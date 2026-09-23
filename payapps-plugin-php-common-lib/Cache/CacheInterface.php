<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

interface CacheInterface
{
    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 0): bool;

    /**
     * @param string $key
     *
     * @return bool
     */
    public function remove(string $key): bool;
}
