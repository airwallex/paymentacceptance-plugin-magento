<?php

namespace Airwallex\Payments\Model\Methods;

use Magento\Quote\Api\Data\CartInterface;

class ExpressCheckout extends CardMethod
{
    public const CODE = 'airwallex_payments_express';
}
