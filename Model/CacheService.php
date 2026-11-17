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
use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\App\CacheInterface as MagentoCacheInterface;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;
use Throwable;

class CacheService implements CacheInterface
{
    use CacheTrait;

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

    public function __construct(?string $clientId = null, ?string $apiKey = null)
    {
        $this->cache = ObjectManager::getInstance()->get(MagentoCacheInterface::class);
        $this->logger = ObjectManager::getInstance()->get(LoggerInterface::class);
        if ($clientId === null && $apiKey === null) {
            $initClientId = Init::getInstance()->get('client_id');
            $initApiKey = Init::getInstance()->get('api_key');
            if ($initClientId !== null || $initApiKey !== null) {
                $clientId = (string) ($initClientId ?? '');
                $apiKey = (string) ($initApiKey ?? '');
            } else {
                $configuration = ObjectManager::getInstance()->get(Configuration::class);
                $clientId = (string) $configuration->getClientId();
                $apiKey = (string) $configuration->getApiKey();
            }
        }
        $this->prefix = $this->buildPrefix((string) $clientId, (string) $apiKey);
    }

    private function currentPrefix(): string
    {
        $initClientId = Init::getInstance()->get('client_id');
        $initApiKey = Init::getInstance()->get('api_key');
        if ($initClientId !== null || $initApiKey !== null) {
            return $this->buildPrefix((string) ($initClientId ?? ''), (string) ($initApiKey ?? ''));
        }
        return $this->prefix;
    }

    private function buildPrefix(string $clientId, string $apiKey): string
    {
        return 'awx_' . hash('sha256', $clientId . '-' . $apiKey) . '_';
    }

    private function unserializeItem(array $item)
    {
        // Keep strict nested JSON validation before common-lib restores an allowed struct.
        if (($item['class_type'] ?? '') !== '' && is_string($item['raw_data'] ?? null)) {
            $item['raw_data'] = json_decode($item['raw_data'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($item['raw_data'])) {
                throw new \UnexpectedValueException('Invalid cached struct data.');
            }
        }
        return $this->deserializeSingleItem($item);
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
                ? array_map([$this, 'serializeSingleItem'], $value)
                : $this->serializeSingleItem($value);

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return $this->cache->save($json, $this->currentPrefix() . $key, [], $maxAge);
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
        $value = $this->cache->load($this->currentPrefix() . $key);
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
        return $this->cache->remove($this->currentPrefix() . $key);
    }
}
