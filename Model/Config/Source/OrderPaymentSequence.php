<?php

namespace Airwallex\Payments\Model\Config\Source;

use Airwallex\Payments\Api\OrderServiceInterface;
use Magento\Framework\Data\OptionSourceInterface;

class OrderPaymentSequence implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => OrderServiceInterface::PAYMENT_BEFORE_ORDER,
                'label' => __('Payment First, Then Order Creation')
            ],
            [
                'value' => OrderServiceInterface::ORDER_BEFORE_PAYMENT,
                'label' => __('Order First, Then Payment Confirmation')
            ],
        ];
    }
}
