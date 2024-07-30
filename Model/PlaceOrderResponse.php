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
    /**
     * @return string|null
     */
    public function getIntentId()
    {
        return $this->getData(self::DATA_KEY_INTENT_ID);
    }

    /**
     * @param string|null $intentId
     * @return PlaceOrderResponse
     */
    public function setIntentId(string $intentId = null)
    {
        return $this->setData(self::DATA_KEY_INTENT_ID, $intentId);
    }

    /**
     * @return string|null
     */
    public function getRedirectUrl()
    {
        return $this->getData(self::DATA_KEY_REDIRECT_URL);
    }

    /**
     * @param string|null $redirectUrl
     * @return PlaceOrderResponse
     */
    public function setRedirectUrl(string $redirectUrl = null)
    {
        return $this->setData(self::DATA_KEY_INTENT_ID, $redirectUrl);
    }

    /**
     * @return string|null
     */
    public function getClientSecret()
    {
        return $this->getData(self::DATA_KEY_CLIENT_SECRET);
    }

    /**
     * @param string|null $clientSecret
     * @return PlaceOrderResponse
     */
    public function setClientSecret(string $clientSecret = null)
    {
        return $this->setData(self::DATA_KEY_CLIENT_SECRET, $clientSecret);
    }

    /**
     * @return string
     */
    public function getResponseType()
    {
        return $this->getData(self::DATA_KEY_RESPONSE_TYPE);
    }

    /**
     * @param string $responseType
     * @return PlaceOrderResponse
     */
    public function setResponseType(string $responseType)
    {
        return $this->setData(self::DATA_KEY_RESPONSE_TYPE, $responseType);
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->getData(self::DATA_KEY_MESSAGE);
    }

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message)
    {
        return $this->setData(self::DATA_KEY_MESSAGE, $message);
    }

    /**
     * @return int|null
     */
    public function getOrderId()
    {
        return $this->getData(self::DATA_KEY_ORDER_ID);
    }

    /**
     * @param int|null $orderId
     * @return PlaceOrderResponse
     */
    public function setOrderId(int $orderId = null)
    {
        return $this->setData(self::DATA_KEY_ORDER_ID, $orderId);
    }
}
