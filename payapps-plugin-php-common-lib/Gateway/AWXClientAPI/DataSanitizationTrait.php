<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI;

use Airwallex\PayappsPlugin\CommonLibrary\Util\StringHelper;

trait DataSanitizationTrait
{
    /**
     * Sanitize address data
     *
     * @param array $address
     * @return array
     */
    protected function sanitizeAddressData(array $address)
    {
        $sanitized = [];

        if (isset($address['country_code'])) {
            $sanitized['country_code'] = strtoupper(StringHelper::sanitize($address['country_code'], 2, false));
        }

        if (isset($address['state'])) {
            $sanitized['state'] = StringHelper::sanitize($address['state'], 100);
        }

        if (isset($address['city'])) {
            $sanitized['city'] = StringHelper::sanitize($address['city'], 100);
        }

        if (isset($address['street'])) {
            $sanitized['street'] = StringHelper::sanitize($address['street'], 1000);
        }

        if (isset($address['postcode'])) {
            $sanitized['postcode'] = StringHelper::sanitize($address['postcode'], 10, false);
        }

        return $sanitized;
    }
}
