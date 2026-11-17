<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Logger;

class Redactor
{
    public static function redact($value, int $depth = 0)
    {
        // Logs must stay bounded even when callers supply cyclic objects or arrays.
        if ($depth >= 10) {
            return '[redacted]';
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && self::isSensitiveKey($key)) {
                    $result[$key] = '[redacted]';
                } elseif (is_string($key) && self::isPhoneKey($key) && is_scalar($item)) {
                    $result[$key] = self::maskPhone((string) $item);
                } else {
                    $result[$key] = self::redact($item, $depth + 1);
                }
            }
            return $result;
        }
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $encoded = json_encode(self::redact($decoded, $depth + 1), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? '[redacted]' : $encoded;
        }
        return self::text($value, $depth === 0);
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));
        return in_array($normalized, [
            'apikey', 'clientid', 'clientsecret', 'token', 'accesstoken',
            'refreshtoken', 'authorization', 'xapikey', 'xclientid',
            'secret', 'password', 'webhooksecret', 'tokensigningkey',
        ], true);
    }

    private static function isPhoneKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key));
        return in_array($normalized, [
            'phone', 'phonenumber', 'mobile', 'mobilenumber', 'tel', 'telephone',
            'billingphone', 'shippingphone',
        ], true);
    }

    public static function text(string $input, bool $maskWholePhone = true): string
    {
        $normalized = self::normalizeTextEscapes($input);
        if ($normalized === null) {
            return '[redacted]';
        }

        $key = '(?:x[-_ ]?)?(?:api[-_ ]?key|client[-_ ]?(?:id|secret))'
            . '|(?:access|refresh)[-_ ]?token|token(?:[-_ ]?signing[-_ ]?key)?'
            . '|webhook[-_ ]?secret|authorization|secret|password';
        // Unstructured text has no reliable secret boundary; discard the tail.
        $masked = preg_replace(
            '/(?<![a-z0-9_])(' . $key . ')(["\x27]?\s*[:=]\s*)[\s\S]*/i',
            '$1$2[redacted]',
            $normalized
        ) ?? '[redacted]';
        $masked = preg_replace('/\b(Bearer|Basic)\s+[^\s,;}"\x27]+/i', '$1 [redacted]', $masked) ?? '[redacted]';
        $masked = preg_replace_callback(
            '/(?<![A-Za-z0-9_-])[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*(?![A-Za-z0-9_-])/',
            function ($matches) {
                $parts = explode('.', $matches[0]);
                $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true), true);
                return is_array($header) && isset($header['alg']) ? '[redacted]' : $matches[0];
            },
            $masked
        ) ?? '[redacted]';
        $masked = preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            function ($matches) {
                list($user, $domain) = explode('@', $matches[0], 2);
                $length = strlen($user);
                return substr($user, 0, 1) . str_repeat('*', max(0, $length - ($length > 2 ? 2 : 1)))
                    . ($length > 2 ? substr($user, -1) : '') . '@' . $domain;
            },
            $masked
        ) ?? '[redacted]';
        if ($maskWholePhone && preg_match('/^\+?\d{6,20}$/', $masked)) {
            return self::maskPhone($masked);
        }
        $masked = preg_replace_callback(
            '/(\b(?:phone(?:[-_ ]?number)?|mobile(?:[-_ ]?number)?|tel|telephone|(?:billing|shipping)[-_ ]?phone)\b["\x27]?\s*[:=]\s*["\x27]?)(\+?\d[\d ().-]{4,}\d)/i',
            function ($matches) {
                return $matches[1] . self::maskPhone($matches[2]);
            },
            $masked
        ) ?? '[redacted]';
        $masked = preg_replace_callback(
            '/(?<!\d)\+\d{6,20}(?!\d)/',
            function ($matches) {
                return self::maskPhone($matches[0]);
            },
            $masked
        ) ?? '[redacted]';
        if ($normalized !== $input) {
            // Keep original escapes unless masking is needed; decoded control characters must not enter logs.
            return $masked === $normalized ? $input : '[redacted]';
        }
        return is_string($masked) ? $masked : '[redacted]';
    }

    private static function normalizeTextEscapes(string $input)
    {
        for ($depth = 0; $depth < 10; $depth++) {
            $decoded = preg_replace_callback(
                '~\\\\(?:u[0-9a-f]{4}|["\\\\/])~i',
                function ($matches) {
                    $value = json_decode('"' . $matches[0] . '"', true);
                    return is_string($value) ? $value : $matches[0];
                },
                $input
            );
            if ($decoded === null || $decoded === $input) {
                return $decoded;
            }
            $input = $decoded;
        }
        return null;
    }

    private static function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 5) {
            return '[redacted]';
        }
        return substr($phone, 0, 3) . str_repeat('*', max(1, $length - 5)) . substr($phone, -2);
    }
}
