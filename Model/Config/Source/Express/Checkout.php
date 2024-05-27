<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class Checkout
{
    public function toOptionArray()
    {
        return [
            [
                'value' => "google_pay",
                'label' => __('Google Pay'),
            ],
            [
                'value' => "apple_pay",
                'label' => __('Apple Pay'),
            ]
        ];
    }
}
