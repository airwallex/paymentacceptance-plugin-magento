<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType;

class Klarna
{
    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'AT' => 'EUR',
        'AU' => 'AUD',
        'BE' => 'EUR',
        'CA' => 'CAD',
        'FI' => 'EUR',
        'FR' => 'EUR',
        'DE' => 'EUR',
        'GR' => 'EUR',
        'IE' => 'EUR',
        'IT' => 'EUR',
        'NL' => 'EUR',
        'PT' => 'EUR',
        'ES' => 'EUR',
        'DK' => 'DKK',
        'NO' => 'NOK',
        'PL' => 'PLN',
        'SE' => 'SEK',
        'CH' => 'CHF',
        'GB' => 'GBP',
        'CZ' => 'CZK',
        'US' => 'USD',
        'NZ' => 'NZD',
    ];

    const COUNTRY_LANGUAGE = [
        'AT' => ['de'],
        'BE' => ['be', 'nl', 'fr'],
        'CA' => ['fr'],
        'CH' => ['it', 'de', 'fr'],
        'CZ' => ['cs'],
        'DE' => ['de'],
        'DK' => ['da'],
        'ES' => ['es', 'ca'],
        'FI' => ['fi', 'sv'],
        'FR' => ['fr'],
        'GR' => ['el'],
        'IT' => ['it'],
        'NL' => ['nl'],
        'NO' => ['nb'],
        'PL' => ['pl'],
        'PT' => ['pt'],
        'SE' => ['sv'],
        'US' => ['es'],
    ];
}
