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

    public function convertCcType(string $type) : string {
        if (strtolower($type) === 'jcb') {
            return 'jcb';
        }
        if (strtolower($type) === 'visa') {
            return 'vi';
        }
        if (strtolower($type) === 'discover') {
            return 'di';
        }
        if (in_array(strtolower($type), ['diners', 'diners club international'])) {
                return 'dn';
        }
        if (in_array(strtolower($type), ['amex', 'american express', 'americanexpress'])) {
            return 'ae';
        }
        if (in_array(strtolower($type), ['unionpay', 'up', 'union pay'])) {
            return 'un';
        }
        if (in_array(strtolower($type), ['mastercard', 'master card'])) {
            return 'mc';
        }
        return strtolower($type);
    }
}
