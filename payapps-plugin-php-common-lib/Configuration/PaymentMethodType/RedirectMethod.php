<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType;

class RedirectMethod
{
    const SUPPORTED_COUNTRY_TO_CURRENCY = [
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'CA' => 'CAD',
        'SG' => 'SGD',
        'UK' => 'GBP',
        'HK' => 'HKD',
        'AT' => 'EUR',
        'BE' => 'EUR',
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
        'ID' => 'IDR',
        'PH' => 'PHP',
        'KR' => 'KRW',
        'MY' => 'MYR',
        'CN' => 'CNY',
        'JP' => 'JPY',
    ];

    const DEFAULT_CURRENCY = [
        'alipaycn' => 'CNY',
        'alipayhk' => 'HKD',
        'dana' => 'IDR',
        'gcash' => 'PHP',
        'kakaopay' => 'KRW',
        'pay_now' => 'SGD',
        'tng' => 'MYR',
        'wechatpay' => 'CNY',
        'ideal' => 'EUR',
    ];

    const SUPPORTED_ENTITY_TO_CURRENCY = [
        'alipaycn' => [
            'AIRWALLEX_HK' => ['HKD', 'USD', 'CNY', 'AUD', 'SGD', 'GBP', 'EUR', 'JPY', 'CHF', 'CAD', 'NZD'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'CNY'],
            'AIRWALLEX_AU' => ['AUD', 'CNY'],
            'AIRWALLEX_UK' => ['EUR', 'GBP', 'USD', 'CNY'],
            'AIRWALLEX_EU' => ['EUR', 'USD', 'CNY'],
        ],
        'alipayhk' => [
            'AIRWALLEX_HK' => ['HKD'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'HKD'],
            'AIRWALLEX_AU' => ['AUD', 'HKD'],
            'AIRWALLEX_UK' => ['HKD'],
            'AIRWALLEX_EU' => ['EUR', 'USD', 'HKD'],
        ],
        'dana' => [
            'AIRWALLEX_HK' => ['HKD', 'IDR'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'IDR'],
            'AIRWALLEX_AU' => ['AUD', 'IDR'],
        ],
        'gcash' => [
            'AIRWALLEX_HK' => ['HKD', 'PHP'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'PHP'],
            'AIRWALLEX_AU' => ['AUD', 'PHP'],
            'AIRWALLEX_EU' => ['EUR', 'USD', 'PHP'],
            'AIRWALLEX_UK' => ['PHP'],
        ],
        'kakaopay' => [
            'AIRWALLEX_HK' => ['HKD', 'KRW'],
            'AIRWALLEX_SG' => ['KRW', 'SGD', 'USD'],
            'AIRWALLEX_AU' => ['KRW', 'AUD'],
            'AIRWALLEX_EU' => ['EUR', 'USD', 'KRW'],
            'AIRWALLEX_UK' => ['KRW'],
        ],
        'pay_now' => [
            'AIRWALLEX_HK' => ['SGD'],
            'AIRWALLEX_CN' => ['SGD'],
            'AIRWALLEX_AU' => ['SGD'],
            'AIRWALLEX_UK' => ['SGD'],
            'AIRWALLEX_EU' => ['SGD'],
            'AIRWALLEX_SG' => ['SGD'],
            'AIRWALLEX_US' => ['SGD'],
            'AIRWALLEX_NZ' => ['SGD'],
            'AIRWALLEX_MY' => ['SGD'],
        ],
        'tng' => [
            'AIRWALLEX_HK' => ['HKD', 'MYR'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'MYR'],
            'AIRWALLEX_AU' => ['AUD', 'MYR'],
            'AIRWALLEX_EU' => ['EUR', 'USD', 'MYR'],
            'AIRWALLEX_UK' => ['MYR'],
        ],
        'wechatpay' => [
            'AIRWALLEX_HK' => ['HKD', 'USD', 'AUD', 'EUR', 'SGD', 'GBP', 'JPY', 'NZD', 'CHF', 'CAD', 'CNY'],
            'AIRWALLEX_SG' => ['SGD', 'USD', 'CNY'],
            'AIRWALLEX_AU' => ['AUD', 'USD', 'CNY'],
            'AIRWALLEX_UK' => ['EUR', 'GBP', 'CNY'],
            'AIRWALLEX_EU' => ['EUR', 'CNY'],
        ],
        'ideal' => [
            'AIRWALLEX_HK' => ['EUR'],
            'AIRWALLEX_AU' => ['EUR'],
            'AIRWALLEX_UK' => ['EUR'],
            'AIRWALLEX_EU' => ['EUR'],
            'AIRWALLEX_SG' => ['EUR'],
            'AIRWALLEX_US' => ['EUR'],
        ],
    ];
}
