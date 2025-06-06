<?php

namespace Airwallex\Payments\Model\Methods;

class BankTransfer extends RedirectCurrencySwitcherMethod
{
    public const CODE = 'airwallex_payments_bank_transfer';

    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'AT' => 'EUR',
        'BE' => 'EUR',
        'FI' => 'EUR',
        'DK' => 'EUR',
        'GR' => 'EUR',
        'IE' => 'EUR',
        'IT' => 'EUR',
        'NL' => 'EUR',
        'PT' => 'EUR',
        'ES' => 'EUR',
        'US' => 'USD',
        'SG' => 'SGD',
        'GB' => 'GBP',
        'UK' => 'GBP',
        'HK' => 'HKD',
    ];

    const SUPPORTED_CURRENCY_TO_COUNTRY = [
        'SGD' => 'SG',
        'EUR' => 'NL',
        'GBP' => 'GB',
        'USD' => 'US',
        'HKD' => 'HK',
    ];
}
