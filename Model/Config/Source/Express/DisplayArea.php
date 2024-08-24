<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class DisplayArea
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => "product_page",
                'label' => __('Product detail pages')
            ],
            [
                'value' => "minicart",
                'label' => __('Minicart')
            ],
            [
                'value' => "cart_page",
                'label' => __('Shopping cart page')
            ],
            [
                'value' => "checkout_page",
                'label' => __('Checkout page')
            ]
        ];
    }
}
