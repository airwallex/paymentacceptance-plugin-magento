<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Util;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class AmountHelper
{
    public static function amountEquals($amountA, $amountB, $currency)
    {
        $decimalPlaces = PaymentIntent::CURRENCY_TO_DECIMAL[strtoupper($currency)] ?? 2;
        $precision = pow(10, $decimalPlaces);
        $minThreshold = 1 / $precision;

        return abs($amountA - $amountB) < $minThreshold;
    }

    public static function convertAmountToBaseAmount($paymentIntent, $amount)
    {
        if (!$paymentIntent->getBaseCurrency() || $paymentIntent->getCurrency() === $paymentIntent->getBaseCurrency()) {
            return $amount;
        }
        if ($paymentIntent->getAmount() == 0) {
            return 0;
        }
        $convertedAmount = $amount / $paymentIntent->getAmount() * $paymentIntent->getBaseAmount();
        return self::formatAmount($convertedAmount, $paymentIntent->getBaseCurrency());
    }

    public static function convertBaseAmountToAmount($paymentIntent, $baseAmount)
    {
        if (!$paymentIntent->getBaseCurrency() || $paymentIntent->getCurrency() === $paymentIntent->getBaseCurrency()) {
            return $baseAmount;
        }
        if ($paymentIntent->getBaseAmount() == 0) {
            return 0;
        }
        $convertedAmount = $baseAmount / $paymentIntent->getBaseAmount() * $paymentIntent->getAmount();
        return self::formatAmount($convertedAmount, $paymentIntent->getCurrency());
    }

    public static function formatAmount($amount, $currency)
    {
        $scale = PaymentIntent::CURRENCY_TO_DECIMAL[strtoupper($currency)] ?? 2;
        return round($amount, $scale);
    }
}
