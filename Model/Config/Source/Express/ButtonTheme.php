<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class ButtonTheme
{
    public function toOptionArray()
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
