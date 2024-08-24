<?php

namespace Airwallex\Payments\Model\ResourceModel;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentIntent extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PaymentIntentInterface::TABLE, PaymentIntentInterface::ID_COLUMN);
    }
}
