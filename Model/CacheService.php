<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheInterface;
use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\App\CacheInterface as MagentoCacheInterface;
use Magento\Framework\App\ObjectManager;

class CacheService implements CacheInterface {
    /**
     * Prefix of the cache key
     *
     * @var string
     */
    private $prefix;

    private $cache;

    public function __construct() {
        $this->cache = ObjectManager::getInstance()->get(MagentoCacheInterface::class);
        /** @var Configuration $configuration */
        $configuration = ObjectManager::getInstance()->get(Configuration::class);
        $this->prefix = 'awx_' . hash('sha256', $configuration->getClientId() . '-' . $configuration->getApiKey()) . '_';
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
        $normalize = function ($item) {
            return [
                'raw_data'   => (is_object($item) && method_exists($item, 'getRawData')) ? $item->getRawData() : $item,
                'class_type' => (is_object($item) && method_exists($item, 'getClassType')) ? $item->getClassType() : "",
            ];
        };

        if (is_array($value) && !empty($value) && is_object($value[0])) {
            $value = array_map($normalize, $value);
        } else {
            $value = $normalize($value);
        }

        $jsonData = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            return false;
        }

        return $this->cache->save($jsonData, $this->prefix . $key, [], $maxAge);
    }

    /**
     * Get cache value according to cache key
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key ) {
        $value = $this->cache->load($this->prefix . $key);
        if (empty($value)) {
            return null;
        }
        $data = json_decode($value, true);
        if ($data === null) {
            return null;
        }
        if (isset($data[0]) && is_array($data[0]) && array_key_exists('raw_data', $data[0])) {
            $result = [];
            foreach ($data as $item) {
                $classType = $item['class_type'] ?? '';
                $rawData   = $item['raw_data'] ?? null;

                if (empty($classType) || !class_exists($classType)) {
                    $result[] = $rawData;
                    continue;
                }

                $result[] = new $classType(
                    is_string($rawData) ? json_decode($rawData, true) : (array)$rawData
                );
            }
            return $result;
        }
        $classType = $data['class_type'] ?? '';
        $rawData   = $data['raw_data'] ?? null;

        if (empty($classType)) {
            return $rawData;
        }

        if (!class_exists($classType)) {
            return null;
        }

        return new $classType(
            is_string($rawData) ? json_decode($rawData, true) : (array)$rawData
        );
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
