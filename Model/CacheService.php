<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheInterface;
use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\App\CacheInterface as MagentoCacheInterface;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;
use Throwable;

class CacheService implements CacheInterface
{
    /**
     * Prefix of the cache key
     *
     * @var string
     */
    private $prefix;

    /**
     * Magento Cache Instance
     *
     * @var MagentoCacheInterface
     */
    private $cache;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private $logger;

    public function __construct()
    {
        $this->cache = ObjectManager::getInstance()->get(MagentoCacheInterface::class);
        $this->logger = ObjectManager::getInstance()->get(LoggerInterface::class);
        /** @var Configuration $configuration */
        $configuration = ObjectManager::getInstance()->get(Configuration::class);
        $this->prefix = 'awx_' . hash('sha256', $configuration->getClientId() . '-' . $configuration->getApiKey()) . '_';
    }

    private function serializeItem($item): array
    {
        return [
            'raw_data' => (is_object($item) && method_exists($item, 'getRawData'))
                ? $item->getRawData()
                : $item,
            'class_type' => (is_object($item) && method_exists($item, 'getClassType'))
                ? $item->getClassType()
                : '',
        ];
    }

    private function unserializeItem(array $item)
    {
        $classType = $item['class_type'] ?? '';
        $rawData = $item['raw_data'] ?? null;

        if ($classType === '') {
            return $rawData;
        }

        if (!class_exists($classType)) {
            return null;
        }

        return new $classType(
            is_string($rawData) ? json_decode($rawData, true, 512, JSON_THROW_ON_ERROR) : (array)$rawData
        );
    }

    /**
     * Set/update the value of a cache key
     *
     * @param string $key
     * @param $value
     * @param int $maxAge
     * @return bool
     */
    public function set(string $key, $value, int $maxAge = 7200): bool
    {
        try {
            $data = is_array($value)
                ? array_map([$this, 'serializeItem'], $value)
                : $this->serializeItem($value);

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return $this->cache->save($json, $this->prefix . $key, [], $maxAge);
        } catch (Throwable $e) {
            $this->logger->info("[CacheService:set][$key] " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache value according to cache key
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key)
    {
        $value = $this->cache->load($this->prefix . $key);
        if (empty($value)) {
            return null;
        }

        try {
            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (isset($data['raw_data'])) {
                return $this->unserializeItem($data);
            }

            $result = [];
            foreach ($data as $item) {
                if (!isset($item['raw_data'])) {
                    return null;
                }
                $result[] = $this->unserializeItem($item);
            }
            return $result;
        } catch (Throwable $e) {
            $this->logger->info("[CacheService:get][$key] JSON decode error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Remove cache value according to cache key
     *
     * @param string $key
     * @return bool True if the cache was deleted, false otherwise.
     */
    public function remove(string $key): bool
    {
        return $this->cache->remove($this->prefix . $key);
    }
}
