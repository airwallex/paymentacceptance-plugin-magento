<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Util;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class AmountHelper
{
    public static function amountEquals($amountA, $amountB, $currency)
    {
        if (!is_numeric($amountA) || !is_numeric($amountB)
            || !is_finite((float) $amountA) || !is_finite((float) $amountB)
        ) {
            return false;
        }

        $decimalPlaces = PaymentIntent::CURRENCY_TO_DECIMAL[strtoupper((string) $currency)] ?? 2;
        return number_format((float) $amountA, $decimalPlaces, '.', '')
            === number_format((float) $amountB, $decimalPlaces, '.', '');
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
        $scale = PaymentIntent::CURRENCY_TO_DECIMAL[strtoupper((string)$currency)] ?? 2;
        return round($amount, $scale);
    }
}
