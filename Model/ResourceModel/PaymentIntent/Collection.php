<?php

namespace Airwallex\Payments\Model\ResourceModel\PaymentIntent;

use Airwallex\Payments\Model\PaymentIntent;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as ResourcePaymentIntent;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PaymentIntent::class, ResourcePaymentIntent::class);
    }
}
