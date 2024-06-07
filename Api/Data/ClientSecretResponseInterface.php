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

namespace Airwallex\Payments\Api\Data;

interface ClientSecretResponseInterface
{
    public const DATA_KEY_CLIENT_SECRET = 'client_secret';
    public const DATA_KEY_EXPIRED_TIME = 'expired_time';

    /**
     * @return string|null
     */
    public function getClientSecret();

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setClientSecret(string $secret = null);

    /**
     * @return string|null
     */
    public function getExpiredTime();

    /**
     * @param string|null $time
     * @return $this
     */
    public function setExpiredTime(string $time = null);
}
