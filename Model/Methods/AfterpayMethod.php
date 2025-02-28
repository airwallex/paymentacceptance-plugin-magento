<?php

namespace Airwallex\Payments\Model\Methods;

class AfterpayMethod extends RedirectCurrencySwitcherMethod
{
    public const CODE = 'airwallex_payments_afterpay';

    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'US' => 'USD',
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'GB' => 'GBP',
        'CA' => 'CAD',
    ];

    const SUPPORTED_ENTITY_TO_CURRENCY = [
        'AIRWALLEX_HK' => ['AUD', 'NZD', 'GBP', 'USD', 'CAD'],
        'AIRWALLEX_AU' => ['AUD'],
        'AIRWALLEX_US' => ['USD'],
        'AIRWALLEX_UK' => ['GBP'],
        'AIRWALLEX_CA' => ['CAD'],
    ];
}
