<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class ButtonType
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'book',
                'label' => __('Book')
            ],
            [
                'value' => 'buy',
                'label' => __('Buy')
            ],
            [
                'value' => 'checkout',
                'label' => __('Checkout')
            ],
            [
                'value' => 'continue',
                'label' => __('Continue')
            ],
            [
                'value' => 'donate',
                'label' => __('Donate')
            ],
            [
                'value' => 'order',
                'label' => __('Order')
            ],
            [
                'value' => 'pay',
                'label' => __('Pay')
            ],
            // [
            //     'value' => 'plain',
            //     'label' => __('Plain')
            // ],
            [
                'value' => 'subscribe',
                'label' => __('Subscribe')
            ]
        ];
    }
}
