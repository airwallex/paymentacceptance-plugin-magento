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

interface PlaceOrderResponseInterface
{
    const DATA_KEY_INTENT_ID = 'intent_id';
    const DATA_KEY_CLIENT_SECRET = 'client_secret';
    const DATA_KEY_RESPONSE_TYPE = 'response_type';
    const DATA_KEY_ORDER_ID = 'order_id';

    /**
     * @return string|null
     */
    public function getIntentId();

    /**
     * @param string|null $intentId
     * @return $this
     */
    public function setIntentId(string $intentId = null);

    /**
     * @return string|null
     */
    public function getClientSecret();

    /**
     * @param string|null $clientSecret
     * @return $this
     */
    public function setClientSecret(string $clientSecret = null);

    /**
     * @return string
     */
    public function getResponseType();

    /**
     * @param string $responseType
     * @return $this
     */
    public function setResponseType(string $responseType);

    /**
     * @return int|null
     */
    public function getOrderId();

    /**
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(int $orderId = null);
}
