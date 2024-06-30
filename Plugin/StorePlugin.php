<?php

namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Model\PaymentIntents;

class StorePlugin
{
    /**
     * @var PaymentIntents
     */
    private PaymentIntents $paymentIntents;

    /**
     * constructor
     *
     * @param PaymentIntents $paymentIntents
     */
    public function __construct(PaymentIntents $paymentIntents)
    {
        $this->paymentIntents = $paymentIntents;
    }

    /**
     * @return void
     */
    public function afterSetCurrentCurrencyCode(): void
    {

    }
}