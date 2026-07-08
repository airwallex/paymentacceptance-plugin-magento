/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 */

define([], function () {
    'use strict';

    const countryToCurrencyMap                         = {
        AD: 'EUR', AE: 'AED', AF: 'AFN', AG: 'XCD', AI: 'XCD',
        AL: 'ALL', AM: 'AMD', AN: 'ANG', AO: 'AOA', AQ: 'USD',
        AR: 'ARS', AS: 'USD', AT: 'EUR', AU: 'AUD', AW: 'AWG',
        AX: 'EUR', AZ: 'AZN', BA: 'BAM', BB: 'BBD', BD: 'BDT',
        BE: 'EUR', BF: 'XOF', BG: 'BGN', BH: 'BHD', BI: 'BIF',
        BJ: 'XOF', BL: 'EUR', BM: 'BMD', BN: 'BND', BO: 'BOB',
        BQ: 'USD', BR: 'BRL', BS: 'BSD', BT: 'BTN', BV: 'NOK',
        BW: 'BWP', BY: 'BYN', BZ: 'BZD', CA: 'CAD', CC: 'AUD',
        CD: 'CDF', CF: 'XAF', CG: 'XAF', CH: 'CHF', CI: 'XOF',
        CK: 'NZD', CL: 'CLP', CM: 'XAF', CN: 'CNY', CO: 'COP',
        CR: 'CRC', CU: 'CUP', CV: 'CVE', CW: 'ANG', CX: 'AUD',
        CY: 'EUR', CZ: 'CZK', DE: 'EUR', DJ: 'DJF', DK: 'DKK',
        DM: 'XCD', DO: 'DOP', DZ: 'DZD', EC: 'USD', EE: 'EUR',
        EG: 'EGP', EH: 'MAD', ER: 'ERN', ES: 'EUR', ET: 'ETB',
        FI: 'EUR', FJ: 'FJD', FK: 'FKP', FM: 'USD', FO: 'DKK',
        FR: 'EUR', GA: 'XAF', GB: 'GBP', GD: 'XCD', GE: 'GEL',
        GF: 'EUR', GG: 'GBP', GH: 'GHS', GI: 'GIP', GL: 'DKK',
        GM: 'GMD', GN: 'GNF', GP: 'EUR', GQ: 'XAF', GR: 'EUR',
        GS: 'FKP', GT: 'GTQ', GU: 'USD', GW: 'XOF', GY: 'GYD',
        HK: 'HKD', HM: 'AUD', HN: 'HNL', HR: 'EUR', HT: 'HTG',
        HU: 'HUF', ID: 'IDR', IE: 'EUR', IL: 'ILS', IM: 'GBP',
        IN: 'INR', IO: 'USD', IQ: 'IQD', IR: 'IRR', IS: 'ISK',
        IT: 'EUR', JE: 'GBP', JM: 'JMD', JO: 'JOD', JP: 'JPY',
        KE: 'KES', KG: 'KGS', KH: 'KHR', KI: 'AUD', KM: 'KMF',
        KN: 'XCD', KP: 'KPW', KR: 'KRW', KW: 'KWD', KY: 'KYD',
        KZ: 'KZT', LA: 'LAK', LB: 'LBP', LC: 'XCD', LI: 'CHF',
        LK: 'LKR', LR: 'LRD', LS: 'LSL', LT: 'EUR', LU: 'EUR',
        LV: 'EUR', LY: 'LYD', MA: 'MAD', MC: 'EUR', MD: 'MDL',
        ME: 'EUR', MF: 'EUR', MG: 'MGA', MH: 'USD', MK: 'MKD',
        ML: 'XOF', MM: 'MMK', MN: 'MNT', MO: 'MOP', MP: 'USD',
        MQ: 'EUR', MR: 'MRU', MS: 'XCD', MT: 'EUR', MU: 'MUR',
        MV: 'MVR', MW: 'MWK', MX: 'MXN', MY: 'MYR', MZ: 'MZN',
        NA: 'NAD', NC: 'XPF', NE: 'XOF', NF: 'AUD', NG: 'NGN',
        NI: 'NIO', NL: 'EUR', NO: 'NOK', NP: 'NPR', NR: 'AUD',
        NU: 'NZD', NZ: 'NZD', OM: 'OMR', PA: 'PAB', PE: 'PEN',
        PF: 'XPF', PG: 'PGK', PH: 'PHP', PK: 'PKR', PL: 'PLN',
        PM: 'EUR', PN: 'NZD', PR: 'USD', PS: 'ILS', PT: 'EUR',
        PW: 'USD', PY: 'PYG', QA: 'QAR', RE: 'EUR', RO: 'RON',
        RS: 'RSD', RU: 'RUB', RW: 'RWF', SA: 'SAR', SB: 'SBD',
        SC: 'SCR', SD: 'SDG', SE: 'SEK', SG: 'SGD', SH: 'SHP',
        SI: 'EUR', SJ: 'NOK', SK: 'EUR', SL: 'SLE', SM: 'EUR',
        SN: 'XOF', SO: 'SOS', SR: 'SRD', SS: 'SSP', ST: 'STN',
        SV: 'USD', SX: 'ANG', SY: 'SYP', SZ: 'SZL', TC: 'USD',
        TD: 'XAF', TF: 'EUR', TG: 'XOF', TH: 'THB', TJ: 'TJS',
        TK: 'NZD', TL: 'USD', TM: 'TMT', TN: 'TND', TO: 'TOP',
        TR: 'TRY', TT: 'TTD', TV: 'AUD', TW: 'TWD', TZ: 'TZS',
        UA: 'UAH', UG: 'UGX', UM: 'USD', US: 'USD', UY: 'UYU',
        UZ: 'UZS', VA: 'EUR', VC: 'XCD', VE: 'VES', VG: 'USD',
        VI: 'USD', VN: 'VND', VU: 'VUV', WF: 'XPF', WS: 'WST',
        YE: 'YER', YT: 'EUR', ZA: 'ZAR', ZM: 'ZMW', ZW: 'ZWL'
    };

    const currencyToCountryMap                         = {
        AED: 'AE', AFN: 'AF', ALL: 'AL', AMD: 'AM', ANG: 'CW', AOA: 'AO',
        ARS: 'AR', AUD: 'AU', AWG: 'AW', AZN: 'AZ', BAM: 'BA', BBD: 'BB',
        BDT: 'BD', BGN: 'BG', BHD: 'BH', BIF: 'BI', BMD: 'BM', BND: 'BN',
        BOB: 'BO', BRL: 'BR', BSD: 'BS', BTN: 'BT', BWP: 'BW', BYN: 'BY',
        BZD: 'BZ', CAD: 'CA', CDF: 'CD', CHF: 'CH', CLP: 'CL', CNY: 'CN',
        COP: 'CO', CRC: 'CR', CUP: 'CU', CVE: 'CV', CZK: 'CZ', DJF: 'DJ',
        DKK: 'DK', DOP: 'DO', DZD: 'DZ', EGP: 'EG', ERN: 'ER', ETB: 'ET',
        EUR: 'DE', FJD: 'FJ', FKP: 'FK', GBP: 'GB', GEL: 'GE', GHS: 'GH',
        GIP: 'GI', GMD: 'GM', GNF: 'GN', GTQ: 'GT', GYD: 'GY', HKD: 'HK',
        HNL: 'HN', HTG: 'HT', HUF: 'HU', IDR: 'ID', ILS: 'IL', INR: 'IN',
        IQD: 'IQ', IRR: 'IR', ISK: 'IS', JMD: 'JM', JOD: 'JO', JPY: 'JP',
        KES: 'KE', KGS: 'KG', KHR: 'KH', KMF: 'KM', KPW: 'KP', KRW: 'KR',
        KWD: 'KW', KYD: 'KY', KZT: 'KZ', LAK: 'LA', LBP: 'LB', LKR: 'LK',
        LRD: 'LR', LSL: 'LS', LYD: 'LY', MAD: 'MA', MDL: 'MD', MGA: 'MG',
        MKD: 'MK', MMK: 'MM', MNT: 'MN', MOP: 'MO', MRU: 'MR', MUR: 'MU',
        MVR: 'MV', MWK: 'MW', MXN: 'MX', MYR: 'MY', MZN: 'MZ', NAD: 'NA',
        NGN: 'NG', NIO: 'NI', NOK: 'NO', NPR: 'NP', NZD: 'NZ', OMR: 'OM',
        PAB: 'PA', PEN: 'PE', PGK: 'PG', PHP: 'PH', PKR: 'PK', PLN: 'PL',
        PYG: 'PY', QAR: 'QA', RON: 'RO', RSD: 'RS', RUB: 'RU', RWF: 'RW',
        SAR: 'SA', SBD: 'SB', SCR: 'SC', SDG: 'SD', SEK: 'SE', SGD: 'SG',
        SHP: 'SH', SLE: 'SL', SOS: 'SO', SRD: 'SR', SSP: 'SS', STN: 'ST',
        SYP: 'SY', SZL: 'SZ', THB: 'TH', TJS: 'TJ', TMT: 'TM', TND: 'TN',
        TOP: 'TO', TRY: 'TR', TTD: 'TT', TWD: 'TW', TZS: 'TZ', UAH: 'UA',
        UGX: 'UG', USD: 'US', UYU: 'UY', UZS: 'UZ', VES: 'VE', VND: 'VN',
        VUV: 'VU', WST: 'WS', XAF: 'CF', XCD: 'AG', XOF: 'BJ', XPF: 'NC',
        YER: 'YE', ZAR: 'ZA', ZMW: 'ZM', ZWL: 'ZW'
    };

    const NEUTRAL_COUNTRY_CODES = new Set(['TW']);

    function currencyToFlagCode(currency        )                {
        const upper = (currency || '').toUpperCase();
        const country = currencyToCountryMap[upper];
        if (!country) {
            return null;
        }
        return NEUTRAL_COUNTRY_CODES.has(country) ? 'WORLD' : country;
    }

    function countryToCurrency(country        )                {
        const upper = (country || '').toUpperCase();
        return upper && upper in countryToCurrencyMap ? countryToCurrencyMap[upper] : null;
    }

    return {
        currencyToFlagCode: currencyToFlagCode,
        currencyToCountryMap: currencyToCountryMap,
        countryToCurrency: countryToCurrency,
        countryToCurrencyMap: countryToCurrencyMap
    };
});
