<?php

namespace Airwallex\Payments\Model\Config\Source\Express\ApplePay;

class ButtonType
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'add-money',
                'label' => __('Add money')
            ],
            [
                'value' => 'book',
                'label' => __('Book')
            ],
            [
                'value' => 'buy',
                'label' => __('Buy')
            ],
            [
                'value' => 'check-out',
                'label' => __('Check-out')
            ],
            [
                'value' => 'continue',
                'label' => __('Continue')
            ],
            [
                'value' => 'contribute',
                'label' => __('Contribute')
            ],
            [
                'value' => 'donate',
                'label' => __('Donate')
            ],
            [
                'value' => 'order',
                'label' => __('Order')
            ],
            // [
            //     'value' => 'plain',
            //     'label' => __('Plain')
            // ],
            [
                'value' => 'reload',
                'label' => __('Reload')
            ],
            [
                'value' => 'rent',
                'label' => __('Rent')
            ],
            [
                'value' => 'subscribe',
                'label' => __('Subscribe')
            ],
            [
                'value' => 'support',
                'label' => __('Support')
            ],
            [
                'value' => 'tip',
                'label' => __('Tip')
            ],
            [
                'value' => 'top-up',
                'label' => __('Top-up')
            ]
        ];
    }
}
