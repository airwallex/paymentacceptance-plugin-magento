<?php

namespace Airwallex\Payments\Model\Config\Source\Express\ApplePay;

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
            ],
            [
                'value' => 'white-outline',
                'label' => __('White with outline')
            ]
        ];
    }
}
