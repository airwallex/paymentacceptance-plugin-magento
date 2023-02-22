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

interface PaymentIntentInterface
{
    public const TABLE = 'airwallex_payment_intents';
    public const ID_COLUMN = 'id';
    public const PAYMENT_INTENT_ID_COLUMN = 'payment_intent_id';
    public const ORDER_INCREMENT_ID_COLUMN = 'order_increment_id';
    public const INTENT_STATUS_REQUIRES_CAPTURE = 'REQUIRES_CAPTURE';

    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return string
     */
    public function getPaymentIntentId(): string;

    /**
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * @param string $paymentIntentId
     *
     * @return $this
     */
    public function setPaymentIntentId(string $paymentIntentId): self;

    /**
     * @param string $orderIncrementId
     *
     * @return $this
     */
    public function setOrderIncrementId(string $orderIncrementId): self;
}
