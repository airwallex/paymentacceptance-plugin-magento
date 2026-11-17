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
namespace Airwallex\Payments\Model;

/**
 * Pure helper for the client checkout funnel telemetry (EPP-1214, spec §4 Path A).
 *
 * Deliberately dependency-free so the whitelist / validation / message assembly
 * can be unit-tested in isolation, without loading the wider service graph.
 */
class CheckoutFunnelEvent
{
    /**
     * Maximum stored length for free-form string fields.
     */
    public const MAX_SESSION_LENGTH = 64;
    public const MAX_METHOD_LENGTH = 64;
    public const MAX_REASON_LENGTH = 255;

    /**
     * Abuse guards for the anonymous relay endpoint.
     */
    public const RATE_LIMIT_MAX = 60;
    public const RATE_LIMIT_WINDOW = 60;
    public const MAX_PAYLOAD_BYTES = 2048;

    /**
     * Airtracker event name. Every funnel stage uses this name; the stage itself
     * is the message "event" field.
     */
    public const LOG_EVENT_NAME = 'onCheckoutFunnel';

    /**
     * Whitelisted checkout funnel stage event names.
     */
    public const EVENTS = [
        'CHECKOUT_ELEMENT_RENDERED',
        'PAYMENT_METHOD_SELECTED',
        'PAYMENT_SUBMITTED',
        'PAYMENT_INTENT_CREATED',
        'PAYMENT_INTENT_CONFIRMED',
        'CHECKOUT_SUCCEEDED',
        'CHECKOUT_FAILED',
        'ORDER_STATUS_UPDATED',
    ];

    /**
     * @param string $eventName
     * @return bool
     */
    public static function isValidEvent(string $eventName): bool
    {
        return in_array($eventName, self::EVENTS, true);
    }

    /**
     * Magento statuses that mean the shopper has paid.
     *
     * @param string $status
     * @return bool
     */
    public static function isPaidOrderStatus(string $status): bool
    {
        return in_array($status, ['processing', 'complete'], true);
    }

    /**
     * Intent id echoed into the confirm log. Rejects anything that could change
     * the apiRoute path.
     *
     * @param string $intentId
     * @return string Empty when the id is blank or not a single path segment.
     */
    public static function normalizeIntentId(string $intentId): string
    {
        $intentId = trim($intentId);
        if ($intentId === '' || strlen($intentId) > self::MAX_SESSION_LENGTH) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $intentId)) {
            return '';
        }

        return $intentId;
    }

    /**
     * Browser or server confirm, reported as PAYMENT_INTENT_CONFIRMED inside onCheckoutFunnel.
     *
     * @param string $sessionId
     * @param string $intentId Already normalized.
     * @param string $paymentMethod
     * @return array
     */
    public static function buildConfirmMessage(string $sessionId, string $intentId, string $paymentMethod): array
    {
        return self::buildMessage(
            'PAYMENT_INTENT_CONFIRMED',
            $sessionId,
            self::funnelPaymentMethod($paymentMethod, $paymentMethod),
            false,
            '',
            ['intent_id' => $intentId]
        );
    }

    /**
     * Alias for {@see self::buildConfirmMessage()}.
     *
     * @param string $sessionId
     * @param string $intentId Already normalized.
     * @param string $paymentMethod
     * @return array
     */
    public static function confirmLogContext(string $sessionId, string $intentId, string $paymentMethod): array
    {
        return self::buildConfirmMessage($sessionId, $intentId, $paymentMethod);
    }

    /**
     * Trim, length-cap and character-validate a client-supplied session id.
     *
     * @param string $sessionId
     * @return string Empty string when the id is blank or contains invalid characters.
     */
    public static function normalizeSessionId(string $sessionId): string
    {
        $sessionId = substr(trim($sessionId), 0, self::MAX_SESSION_LENGTH);
        if ($sessionId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $sessionId)) {
            return '';
        }

        return $sessionId;
    }

    /**
     * PA method type used on funnel logs (klarna, card, googlepay). Magento
     * codes lose their plugin prefix. A saved card is still card. Express
     * wallets use the wallet name when the caller knows it.
     *
     * @param string $methodCode
     * @param string $from
     * @return string
     */
    public static function funnelPaymentMethod(string $methodCode, string $from = ''): string
    {
        $from = strtolower(trim($from));
        if (in_array($from, ['googlepay', 'applepay'], true)) {
            return $from;
        }
        if (in_array($from, ['card', 'vault', 'card_with_saved'], true)) {
            return 'card';
        }

        $code = str_replace(['airwallex_payments_', 'airwallex_cc_', 'airwallex_'], '', $methodCode);
        if ($code === 'card' || $code === 'vault') {
            return 'card';
        }
        if ($code === 'main') {
            return 'apm';
        }
        if ($code === 'wechat') {
            return 'wechatpay';
        }

        return (($code === 'express' || $code === 'express_checkout') && $from !== '') ? $from : $code;
    }

    /**
     * Read the browser tracking id from a place-order additional_data payload.
     * Magento may hand the field over as an array or as a JSON string.
     *
     * @param mixed $additionalData
     * @return string Empty when the id is absent.
     */
    public static function sessionIdFromAdditionalData($additionalData): string
    {
        if (is_string($additionalData) && $additionalData !== '') {
            $decoded = json_decode($additionalData, true);
            $additionalData = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($additionalData)) {
            return '';
        }
        $sessionId = $additionalData['awx_session_id'] ?? '';
        if (!is_string($sessionId)) {
            return '';
        }

        return self::normalizeSessionId($sessionId);
    }

    /**
     * Assemble the funnel event message body (JSON-encoded into the log message).
     * The airtracker event name is always onCheckoutFunnel; the stage is "event".
     * Optional fields are omitted when empty so the emitted log stays compact.
     * The random tracking id lives here (not in commonData) because the common-
     * library logger stamps its own per-request sessionId.
     *
     * @param string $eventName Whitelisted funnel stage.
     * @param string $sessionId Already-normalized tracking id. Omitted when empty.
     * @param string $paymentMethod
     * @param bool $isDefault
     * @param string $errorReason
     * @param array $extra Intent id, order status, and other non-empty scalars.
     * @return array
     */
    public static function buildMessage(
        string $eventName,
        string $sessionId,
        string $paymentMethod = '',
        bool $isDefault = false,
        string $errorReason = '',
        array $extra = []
    ): array {
        $message = [
            'funnel' => 'checkout',
            'event' => $eventName,
        ];
        if ($sessionId !== '') {
            $message['session_id'] = $sessionId;
        }
        if ($paymentMethod !== '') {
            $message['payment_method'] = substr($paymentMethod, 0, self::MAX_METHOD_LENGTH);
        }
        if ($isDefault) {
            $message['is_default'] = true;
        }
        if ($errorReason !== '') {
            $message['error_reason'] = substr($errorReason, 0, self::MAX_REASON_LENGTH);
        }
        foreach ($extra as $key => $value) {
            if ((string) $value !== '') {
                $message[$key] = $value;
            }
        }

        return $message;
    }

    /**
     * Fixed-window rate-limit cache key for a client IP.
     *
     * @param string $clientIp
     * @param int $now Current unix timestamp.
     * @return string
     */
    public static function rateLimitKey(string $clientIp, int $now): string
    {
        $window = (int) floor($now / self::RATE_LIMIT_WINDOW);

        return 'awx_funnel_rl_' . sha1($clientIp) . '_' . $window;
    }

    /**
     * Whether the current request should be rejected given how many events the
     * client has already sent in this window.
     *
     * @param int $currentCount
     * @return bool
     */
    public static function isRateLimited(int $currentCount): bool
    {
        return $currentCount >= self::RATE_LIMIT_MAX;
    }

    /**
     * Whether a raw request body is too large to be a legitimate funnel event.
     *
     * @param string $rawBody
     * @return bool
     */
    public static function isPayloadTooLarge(string $rawBody): bool
    {
        return strlen($rawBody) > self::MAX_PAYLOAD_BYTES;
    }
}
