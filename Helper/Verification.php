<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Airwallex\Payments\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Verification
{
    const POW_COMPLEXITY = 3;
    const POW_PREFIX = 'airwallex';
    const POW_SEPARATOR = ':';

    const NONCE_CACHE_TTL = 600; // 10min
    const NONCE_CACHE_PREFIX = 'airwallex_nonce_';
    const NONCE_CACHE_STATUS_CREATED = '1';
    const NONCE_CACHE_STATUS_USED = '2';

    const POW_ENABLE_KEY = 'airwallex/general/pow_enable';

    public function __construct(
        protected CacheInterface $cache,
        protected ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isPOWEnabled()
    {
        return $this->scopeConfig->getValue(self::POW_ENABLE_KEY);
    }

    /**
     * Expected solution in format: PREFIX:RANDOM_STR
     * @param string $solution
     * @return void
     * @throws LocalizedException
     */
    public function validatePOWSolution(string $solution): void
    {
        if (!$this->isPOWEnabled()) {
            return;
        }

        $parts = explode(self::POW_SEPARATOR, $solution);
        if (count($parts) !== 3) {
            throw new LocalizedException(
                __('Solution has invalid format. Expected PREFIX:RANDOM_STR, got: %1', $solution)
            );
        }

        $nonceStatus = $this->cache->load($this->getNonceCacheID($parts[1]));
        if ($nonceStatus === self::NONCE_CACHE_STATUS_USED) {
            throw new LocalizedException(
                __('Nonce has been used already')
            );
        }
        $this->cache->save(
            self::NONCE_CACHE_STATUS_USED,
            $this->getNonceCacheID($parts[1]),
            [],
            self::NONCE_CACHE_TTL
        );

        if ($parts[0] !== self::POW_PREFIX) {
            throw new LocalizedException(
                __('Solution has wrong prefix. Expected: %1, got: %2', self::POW_PREFIX, $parts[0])
            );
        }

        $hash = hash('sha256', hash('sha256', $solution));
        $startString = str_repeat('0', self::POW_COMPLEXITY);
        if (substr($hash, 0, self::POW_COMPLEXITY) !== $startString) {
            throw new LocalizedException(
                __('Solution %1 is incorrect.', $solution)
            );
        }
    }

    /**
     * @return string
     * @throws \Random\RandomException
     */
    public function getNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $this->cache->save(self::NONCE_CACHE_STATUS_CREATED, $this->getNonceCacheID($nonce));

        return $nonce;
    }

    protected function getNonceCacheID(string $nonce): string
    {
        return self::NONCE_CACHE_PREFIX . $nonce;
    }
}
