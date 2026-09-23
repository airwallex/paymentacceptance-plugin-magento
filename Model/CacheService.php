<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
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
