<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Util;

class StringHelper
{
    /**
     * Sanitize string field with optional length limit
     *
     * @param mixed $value
     * @param int|null $maxLength
     * @param bool $useMultibyte
     * @return string
     */
    public static function sanitize($value, $maxLength = null, $useMultibyte = true)
    {
        $str = trim((string)$value);

        if ($maxLength !== null) {
            return $useMultibyte ? mb_substr($str, 0, $maxLength) : substr($str, 0, $maxLength);
        }

        return $str;
    }
}
