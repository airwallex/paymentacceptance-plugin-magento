<?php

namespace Airwallex\Payments\Model\Config\Source\Express;

class WalletButton
{
    public function toOptionArray()
    {
        return [
            [
                'value' => "product_page",
                'label' => __('Product pages')
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
