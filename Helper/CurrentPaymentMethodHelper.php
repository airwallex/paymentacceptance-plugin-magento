<?php

namespace Airwallex\Payments\Helper;

class CurrentPaymentMethodHelper
{
    public string $paymentMethod = "";

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }
}
