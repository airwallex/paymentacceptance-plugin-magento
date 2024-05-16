<?php

namespace Airwallex\Payments\Model\Traits;

trait HelperTrait
{
    public function convertToDisplayCurrency(float $amount, $rate, $reverse = false) : float
    {
        if (empty($rate)) {
            return $amount;
        }
        if ($reverse) {
            $ret = round(floatval($amount / $rate), 4);
        } else {
            $ret = round(floatval($amount * $rate), 4);
        }
        return is_numeric($ret) ? $ret : 0;
    }
}
