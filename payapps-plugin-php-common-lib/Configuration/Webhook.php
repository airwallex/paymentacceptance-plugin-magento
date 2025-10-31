<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration;

class Webhook
{
    const STATUS_PAYMENT_INTENT_REQUIRES_CAPTURE = 'payment_intent.requires_capture';
    const STATUS_PAYMENT_INTENT_CAPTURE_REQUIRED = 'payment_intent.capture_required';
    const STATUS_PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    const STATUS_PAYMENT_INTENT_CAPTURE_REQUESTED = 'payment_intent.capture_requested';
    const STATUS_PAYMENT_INTENT_REQUESTED_CAPTURE = 'payment_intent.requested_capture';
    const STATUS_PAYMENT_INTENT_CANCELLED = 'payment_intent.cancelled';
    const STATUS_REFUND_ACCEPTED = 'refund.accepted';
    const STATUS_REFUND_PROCESSING = 'refund.processing';

    public static function isPaymentIntentAuthorized(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PAYMENT_INTENT_REQUIRES_CAPTURE,
            self::STATUS_PAYMENT_INTENT_CAPTURE_REQUIRED,
        ], true);
    }

    public static function isPaymentIntentCaptured(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PAYMENT_INTENT_SUCCEEDED,
            self::STATUS_PAYMENT_INTENT_CAPTURE_REQUESTED,
            self::STATUS_PAYMENT_INTENT_REQUESTED_CAPTURE,
        ], true);
    }

    public static function isRefundAccepted(string $status): bool
    {
        return in_array($status, [
            self::STATUS_REFUND_ACCEPTED,
            self::STATUS_REFUND_PROCESSING,
        ], true);
    }
}