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

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\ClientSecretResponseInterface;
use Magento\Framework\DataObject;

class ClientSecretResponse extends DataObject implements ClientSecretResponseInterface
{
        /**
     * @return string|null
     */
    public function getClientSecret()
    {
        return $this->getData(self::DATA_KEY_CLIENT_SECRET);
    }

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setClientSecret(string $secret = null)
    {
        return $this->setData(self::DATA_KEY_CLIENT_SECRET, $secret);
    }

    /**
     * @return string|null
     */
    public function getExpiredTime()
    {
        return $this->getData(self::DATA_KEY_EXPIRED_TIME);
    }

    /**
     * @param string|null $time
     * @return $this
     */
    public function setExpiredTime(string $time = null)
    {
        return $this->setData(self::DATA_KEY_EXPIRED_TIME, $secret);
    }
}
