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

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as ResourcePaymentIntent;

class PaymentIntent extends AbstractModel implements IdentityInterface, PaymentIntentInterface
{
    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourcePaymentIntent::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return ['airwallex_payment_intents' . '_' . $this->getId()];
    }

    /**
     * @return string
     */
    public function getPaymentIntentId(): string
    {
        return $this->getData(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN);
    }

    /**
     * @return string
     */
    public function getOrderIncrementId(): string
    {
        return $this->getData(PaymentIntentInterface::ORDER_INCREMENT_ID_COLUMN);
    }

    /**
     * @param string $paymentIntentId
     *
     * @return PaymentIntentInterface
     */
    public function setPaymentIntentId(string $paymentIntentId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::PAYMENT_INTENT_ID_COLUMN, $paymentIntentId);
    }

    /**
     * @param string $orderIncrementId
     *
     * @return PaymentIntentInterface
     */
    public function setOrderIncrementId(string $orderIncrementId): PaymentIntentInterface
    {
        return $this->setData(PaymentIntentInterface::ORDER_INCREMENT_ID_COLUMN, $orderIncrementId);
    }
}
