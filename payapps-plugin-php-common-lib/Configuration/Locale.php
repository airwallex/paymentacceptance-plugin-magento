<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration;

class Locale
{
    // Mirrors the PA checkout UI's supported locales.
    // Update this list if PA adds or removes locale support.
    const SUPPORTED_LOCALES = [
        'en', 'zh', 'ja', 'ko', 'ar', 'fr', 'es', 'nl', 'de', 'it',
        'zh-HK', 'pl', 'fi', 'ru', 'da', 'id', 'ms', 'sv', 'ro', 'pt',
    ];
}
