<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Util;

class UrlHelper
{
    /**
     * Reduce a URL to its origin (scheme://host[:port]/), stripping any
     * path/query/fragment.
     *
     * Useful for the merchant_website_url Payment Intent field (Mastercard
     * AN 6022), but not tied to it. Returns an empty string when the input is
     * missing or not an http(s) URL, so callers can omit the value rather than
     * pass something malformed.
     *
     * @param string $url
     * @return string
     */
    public static function toOrigin(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (!$scheme || !$host) {
            return '';
        }

        $scheme = strtolower($scheme);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        // Bracket IPv6 literal hosts so the rebuilt origin is valid. parse_url()
        // keeps the brackets on current PHP, so only add them when missing to
        // avoid double-wrapping (e.g. "[::1]").
        if (strpos($host, ':') !== false && strpos($host, '[') === false) {
            $host = '[' . $host . ']';
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $scheme . '://' . $host . ($port ? ':' . $port : '') . '/';
    }
}
