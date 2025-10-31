<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheInterface;
use Magento\Framework\App\CacheInterface as MagentoCacheInterface;
use Magento\Framework\App\ObjectManager;

class CacheService implements CacheInterface {

    const PREFIX = 'awx_';

    /**
     * Prefix of the cache key
     *
     * @var string
     */
    private $prefix;

    private $cache;

    /**
     * Set the prefix according to the salt provided
     *
     * @param string $salt
     */
    public function __construct( $salt = '' ) {
        $this->prefix = self::PREFIX . ( $salt ? md5( $salt ) : '' ) . '_';
        $this->cache = ObjectManager::getInstance()->get(MagentoCacheInterface::class);
    }

    /**
     * Set/update the value of a cache key
     *
     * @param string $key
     * @param $value
     * @param int $maxAge
     * @return bool
     */
    public function set(string $key, $value, int $maxAge = 7200 ): bool {
        if ( !is_string( $value ) ) {
            $value = '__SERIALIZED__' . serialize( $value );
        }
        return $this->cache->save($value, $this->prefix . $key, [], $maxAge);
    }

    /**
     * Get cache value according to cache key
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key ) {
        $value = $this->cache->load($this->prefix . $key);
        if (is_string($value) && strpos($value, '__SERIALIZED__') === 0) {
            return unserialize(substr($value, 14));
        }
        return $value;
    }

    /**
     * Remove cache value according to cache key
     *
     * @param string $key
     * @return bool True if the cache was deleted, false otherwise.
     */
    public function remove( string $key ): bool {
        return $this->cache->remove($this->prefix . $key);
    }
}
