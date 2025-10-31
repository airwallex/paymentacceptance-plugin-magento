<?php

namespace Airwallex\Payments\Helper;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;

class IntentHelper
{
    private StructPaymentIntent $intent;

    /**
     * @return StructPaymentIntent
     */
    public function getIntent(): StructPaymentIntent
    {
        return $this->intent ?? new StructPaymentIntent();
    }

    /**
     * @param StructPaymentIntent $intent
     */
    public function setIntent(StructPaymentIntent $intent): void
    {
        $this->intent = $intent;
    }
}
