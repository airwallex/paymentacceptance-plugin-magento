<?php

namespace Airwallex\Payments\Model\Config\Source\Express\GooglePay;

class ButtonTheme
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'black',
                'label' => __('Black')
            ],
            [
                'value' => 'white',
                'label' => __('White')
            ]
        ];
    }
}
