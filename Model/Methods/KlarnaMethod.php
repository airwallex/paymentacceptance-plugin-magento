<?php

namespace Airwallex\Payments\Model\Methods;

use Magento\Quote\Api\Data\CartInterface;

class KlarnaMethod extends CardMethod
{
    public const CODE = 'airwallex_payments_klarna';

    const SUPPORTED_COUNTRY_TO_CURRENCY = [
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

    public function isAvailable(CartInterface $quote = null): bool
    {
        $codes = $this->availablePaymentMethodsHelper->getLatestItems(false);
        $code = $this->getPaymentMethodCode($this->getCode());
        foreach ($codes as $codeItem) {
            if (!empty($codeItem['name']) && $codeItem['name'] === $code) {
                return true;
            }
        }

        return false;
    }
}
