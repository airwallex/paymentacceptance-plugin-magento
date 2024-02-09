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

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Magento\Framework\DataObject;

class PlaceOrderResponse extends DataObject implements PlaceOrderResponseInterface
{

    public function getIntentId(): string|null
    {
        return $this->getData(self::DATA_KEY_INTENT_ID);
    }

    public function setIntentId(string $intentId = null)
    {
        return $this->setData(self::DATA_KEY_INTENT_ID, $intentId);
    }

    public function getClientSecret(): string|null
    {
        return $this->getData(self::DATA_KEY_CLIENT_SECRET);
    }

    public function setClientSecret(string $clientSecret = null)
    {
        return $this->setData(self::DATA_KEY_CLIENT_SECRET, $clientSecret);
    }

    public function getResponseType(): string
    {
        return $this->getData(self::DATA_KEY_RESPONSE_TYPE);
    }

    public function setResponseType(string $responseType)
    {
        return $this->setData(self::DATA_KEY_RESPONSE_TYPE, $responseType);
    }

    public function getOrderId(): int|null
    {
        return $this->getData(self::DATA_KEY_ORDER_ID);
    }

    public function setOrderId(int $orderId = null)
    {
        return $this->setData(self::DATA_KEY_ORDER_ID, $orderId);
    }
}
