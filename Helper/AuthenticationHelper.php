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

use Airwallex\Payments\Model\Client\Request\Authentication;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\ObjectManagerInterface;

class AuthenticationHelper
{
    private const CACHE_NAME = 'airwallex_token';
    private const CACHE_TIME = 60 * 60; // 1 Hour

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * AuthenticationHelper constructor.
     *
     * @param CacheInterface $cache
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        CacheInterface $cache,
        ObjectManagerInterface $objectManager
    ) {
        $this->cache = $cache;
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     */
    public function getBearerToken(): string
    {
        $token = $this->cache->load(self::CACHE_NAME);

        if (empty($token)) {
            $token = $this->objectManager->create(Authentication::class)->send();
            $this->cache->save($token, self::CACHE_NAME, [], self::CACHE_TIME);
        }

        return $token;
    }
}
