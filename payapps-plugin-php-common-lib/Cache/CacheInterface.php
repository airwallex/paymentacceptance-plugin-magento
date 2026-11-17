<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

/**
 * Host adapters must namespace cache entries by the active Client ID and API Key.
 * Select the matching namespace whenever credentials change.
 */
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
