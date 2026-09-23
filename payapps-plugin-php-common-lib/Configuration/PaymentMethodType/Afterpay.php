<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType;

class Afterpay
{
    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'US' => 'USD',
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'GB' => 'GBP',
        'CA' => 'CAD',
    ];

    const SUPPORTED_ENTITY_TO_CURRENCIES = [
        'AIRWALLEX_HK' => ['AUD', 'NZD', 'GBP', 'USD', 'CAD'],
        'AIRWALLEX_AU' => ['AUD'],
        'AIRWALLEX_US' => ['USD'],
        'AIRWALLEX_UK' => ['GBP'],
        'AIRWALLEX_CA' => ['CAD'],
        'AIRWALLEX_NZ' => ['NZD'],
    ];
}
