<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Cache;

trait CacheTrait
{
    public function cacheRemember(string $key, callable $callback, int $ttl = 3600)
    {
        $cache = CacheManager::getInstance();
        $cachedValue = $cache->get($key);

        if (!empty($cachedValue)) {
            return $cachedValue;
        }

        $value = $callback();
        $cache->set($key, $value, $ttl);
        return $value;
    }

    private function serializeSingleItem($item): array
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

    private function deserializeSingleItem(array $item)
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
            is_string($rawData) ? json_decode($rawData, true) : (array)$rawData
        );
    }

    public function serialize($value): string
    {
        $data = is_array($value)
            ? array_map([$this, 'serializeSingleItem'], $value)
            : $this->serializeSingleItem($value);

        return json_encode($data);
    }

    public function deserialize(string $serializedData)
    {
        if (empty($serializedData)) {
            return null;
        }

        $data = json_decode($serializedData, true);

        if ($data === null) {
            return null;
        }

        if (isset($data['raw_data'])) {
            return $this->deserializeSingleItem($data);
        }

        if (!is_array($data)) {
            return null;
        }

        $result = [];
        foreach ($data as $item) {
            if (!isset($item['raw_data'])) {
                return null;
            }
            $result[] = $this->deserializeSingleItem($item);
        }
        return $result;
    }
}
