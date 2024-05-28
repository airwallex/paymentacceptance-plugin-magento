<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class Checkout
{
    public function toOptionArray()
    {
        return [
            [
                'value' => "apple_pay",
                'label' => __('Apple Pay'),
            ], [
                'value' => "google_pay",
                'label' => __('Google Pay'),
            ],
        ];
    }
}
