<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Logger\Guzzle;

use GuzzleHttp\MessageFormatter as BaseMessageFormatter;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

class MessageFormatter extends BaseMessageFormatter
{
    public const REDACTED = '[REDACTED]';

    private const WITHOUT_HEADERS = [
        'Authorization',
        'x-client-id',
        'x-api-key'
    ];

    private const REDACTED_KEYS = [
        'token', 'access_token', 'refresh_token', 'client_secret', 'api_key', 'webhook_secret',
        'email', 'shopper_email', 'phone', 'phone_number', 'shopper_phone',
        'first_name', 'last_name', 'name', 'shopper_name',
        'street', 'address1', 'address2', 'address3', 'address_line1', 'address_line2',
        'city', 'state', 'postcode', 'postal_code',
        'number', 'expiry_month', 'expiry_year', 'iban', 'account_number', 'routing_number',
        'fingerprint', 'ip_address', 'user_agent',
    ];

    private const PII_CONTAINERS = ['customer', 'billing', 'shipping', 'address', 'card', 'bank_transfer', 'klarna', 'afterpay'];

    public function format(
        RequestInterface   $request,
        ?ResponseInterface $response = null,
        ?Throwable         $error = null
    ): string {
        foreach (self::WITHOUT_HEADERS as $header) {
            $request = $request->withoutHeader($header);
        }

        $request = $this->withRedactedBody($request);
        if ($response !== null) {
            $response = $this->withRedactedBody($response);
        }

        return parent::format($request, $response, $error);
    }

    public static function redactBody(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode(
                self::redactNode($decoded, false),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if ($encoded !== false) {
                return $encoded;
            }
        }

        return (string) preg_replace(
            '/("(?:token|access_token|refresh_token|client_secret|api_key|webhook_secret|email|shopper_email|phone_number|shopper_phone|first_name|last_name|shopper_name|street|postcode|postal_code)"\s*:\s*)"(?:\\\\.|[^"\\\\])*"/i',
            '$1"' . self::REDACTED . '"',
            $body
        );
    }

    private static function redactNode($node, bool $inPiiContainer)
    {
        if (!is_array($node)) {
            return $node;
        }

        $redacted = [];
        foreach ($node as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';
            $childInPii = $inPiiContainer || in_array($normalizedKey, self::PII_CONTAINERS, true);

            if (is_array($value)) {
                $redacted[$key] = self::redactNode($value, $childInPii);
                continue;
            }

            if ($normalizedKey !== '' && self::shouldRedactKey($normalizedKey, $inPiiContainer) && $value !== null && $value !== '') {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private static function shouldRedactKey(string $key, bool $inPiiContainer): bool
    {
        if ($key === 'name') {
            return $inPiiContainer;
        }

        return in_array($key, self::REDACTED_KEYS, true);
    }

    private function withRedactedBody(MessageInterface $message): MessageInterface
    {
        $body = $message->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $raw = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }
        if ($raw === '') {
            return $message;
        }

        return $message->withBody($this->streamFor(self::redactBody($raw)));
    }

    private function streamFor(string $contents): StreamInterface
    {
        if (class_exists(Utils::class) && method_exists(Utils::class, 'streamFor')) {
            return Utils::streamFor($contents);
        }

        return \GuzzleHttp\Psr7\stream_for($contents);
    }
}
