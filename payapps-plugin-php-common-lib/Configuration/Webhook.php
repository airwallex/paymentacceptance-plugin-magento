<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration;

class Webhook
{
    // PaymentIntent
    const STATUS_PAYMENT_INTENT_CREATED = 'payment_intent.created';
    const STATUS_PAYMENT_INTENT_REQUIRES_PAYMENT_METHOD = 'payment_intent.requires_payment_method';
    const STATUS_PAYMENT_INTENT_UPDATED = 'payment_intent.updated';
    const STATUS_PAYMENT_INTENT_REQUIRES_CAPTURE = 'payment_intent.requires_capture';
    const STATUS_PAYMENT_INTENT_CAPTURE_REQUIRED = 'payment_intent.capture_required';
    const STATUS_PAYMENT_INTENT_REQUIRES_CUSTOMER_ACTION = 'payment_intent.requires_customer_action';
    const STATUS_PAYMENT_INTENT_PENDING = 'payment_intent.pending';
    const STATUS_PAYMENT_INTENT_PENDING_REVIEW = 'payment_intent.pending_review';
    const STATUS_PAYMENT_INTENT_SUCCEEDED = 'payment_intent.succeeded';
    const STATUS_PAYMENT_INTENT_CAPTURE_REQUESTED = 'payment_intent.capture_requested';
    const STATUS_PAYMENT_INTENT_REQUESTED_CAPTURE = 'payment_intent.requested_capture';
    const STATUS_PAYMENT_INTENT_CANCELLED = 'payment_intent.cancelled';
    const STATUS_PAYMENT_INTENT_PAYMENT_FAILED = 'payment_intent.payment_failed';

    // PaymentAttempt
    const STATUS_PAYMENT_ATTEMPT_RECEIVED = 'payment_attempt.received';
    const STATUS_PAYMENT_ATTEMPT_AUTHENTICATION_FAILED = 'payment_attempt.authentication_failed';
    const STATUS_PAYMENT_ATTEMPT_AUTHENTICATION_REDIRECTED = 'payment_attempt.authentication_redirected';
    const STATUS_PAYMENT_ATTEMPT_PENDING_AUTHORIZATION = 'payment_attempt.pending_authorization';
    const STATUS_PAYMENT_ATTEMPT_AUTHORIZATION_FAILED = 'payment_attempt.authorization_failed';
    const STATUS_PAYMENT_ATTEMPT_AUTHORIZED = 'payment_attempt.authorized';
    const STATUS_PAYMENT_ATTEMPT_CAPTURE_REQUESTED = 'payment_attempt.capture_requested';
    const STATUS_PAYMENT_ATTEMPT_SETTLED = 'payment_attempt.settled';
    const STATUS_PAYMENT_ATTEMPT_PAID = 'payment_attempt.paid';
    const STATUS_PAYMENT_ATTEMPT_CANCELLED = 'payment_attempt.cancelled';
    const STATUS_PAYMENT_ATTEMPT_EXPIRED = 'payment_attempt.expired';
    const STATUS_PAYMENT_ATTEMPT_RISK_DECLINED = 'payment_attempt.risk_declined';
    const STATUS_PAYMENT_ATTEMPT_FAILED_TO_PROCESS = 'payment_attempt.failed_to_process';
    const STATUS_PAYMENT_ATTEMPT_CAPTURE_FAILED = 'payment_attempt.capture_failed';

    // PaymentConsent
    const STATUS_PAYMENT_CONSENT_CREATED = 'payment_consent.created';
    const STATUS_PAYMENT_CONSENT_UPDATED = 'payment_consent.updated';
    const STATUS_PAYMENT_CONSENT_PENDING = 'payment_consent.pending';
    const STATUS_PAYMENT_CONSENT_VERIFIED = 'payment_consent.verified';
    const STATUS_PAYMENT_CONSENT_DISABLED = 'payment_consent.disabled';
    const STATUS_PAYMENT_CONSENT_PAUSED = 'payment_consent.paused';
    const STATUS_PAYMENT_CONSENT_REQUIRES_PAYMENT_METHOD = 'payment_consent.requires_payment_method';
    const STATUS_PAYMENT_CONSENT_REQUIRES_CUSTOMER_ACTION = 'payment_consent.requires_customer_action';
    const STATUS_PAYMENT_CONSENT_VERIFICATION_FAILED = 'payment_consent.verification_failed';

    // Customer
    const STATUS_CUSTOMER_CREATED = 'customer.created';
    const STATUS_CUSTOMER_UPDATED = 'customer.updated';

    // Refund
    const STATUS_REFUND_RECEIVED = 'refund.received';
    const STATUS_REFUND_ACCEPTED = 'refund.accepted';
    const STATUS_REFUND_PROCESSING = 'refund.processing';
    const STATUS_REFUND_SETTLED = 'refund.settled';
    const STATUS_REFUND_FAILED = 'refund.failed';

    // PaymentMethod
    const STATUS_PAYMENT_METHOD_CREATED = 'payment_method.created';
    const STATUS_PAYMENT_METHOD_UPDATED = 'payment_method.updated';
    const STATUS_PAYMENT_METHOD_ATTACHED = 'payment_method.attached';
    const STATUS_PAYMENT_METHOD_DETACHED = 'payment_method.detached';
    const STATUS_PAYMENT_METHOD_DISABLED = 'payment_method.disabled';

    // Dispute
    const STATUS_PAYMENT_DISPUTE_REQUIRES_RESPONSE = 'payment_dispute.requires_response';
    const STATUS_PAYMENT_DISPUTE_CHALLENGED = 'payment_dispute.challenged';
    const STATUS_PAYMENT_DISPUTE_ACCEPTED = 'payment_dispute.accepted';
    const STATUS_PAYMENT_DISPUTE_EXPIRED = 'payment_dispute.expired';
    const STATUS_PAYMENT_DISPUTE_PENDING_CLOSURE = 'payment_dispute.pending_closure';
    const STATUS_PAYMENT_DISPUTE_PENDING_DECISION = 'payment_dispute.pending_decision';
    const STATUS_PAYMENT_DISPUTE_WON = 'payment_dispute.won';
    const STATUS_PAYMENT_DISPUTE_LOST = 'payment_dispute.lost';
    const STATUS_PAYMENT_DISPUTE_REVERSED = 'payment_dispute.reversed';

    // PaymentLink
    const STATUS_PAYMENT_LINK_CREATED = 'payment_link.created';
    const STATUS_PAYMENT_LINK_PAID = 'payment_link.paid';

    // POS terminal
    const STATUS_POS_TERMINAL_ACTIVATED = 'pos.terminal.activated';
    const STATUS_POS_TERMINAL_DEACTIVATED = 'pos.terminal.deactivated';
    const STATUS_POS_TERMINAL_TERMINATED = 'pos.terminal.terminated';
    const STATUS_POS_TERMINAL_UPDATED = 'pos.terminal.updated';
    const STATUS_POS_TERMINAL_ADMIN_PASSWORD_STATUS_RESET_REQUESTED = 'pos.terminal.admin_password_status.reset_requested';
    const STATUS_POS_TERMINAL_ADMIN_PASSWORD_STATUS_ACTIVATED = 'pos.terminal.admin_password_status.activated';
    const STATUS_POS_TERMINAL_ADMIN_PASSWORD_STATUS_LOCKED = 'pos.terminal.admin_password_status.locked';
    const STATUS_POS_TERMINAL_REFUND_PASSWORD_STATUS_RESET_REQUESTED = 'pos.terminal.refund_password_status.reset_requested';
    const STATUS_POS_TERMINAL_REFUND_PASSWORD_STATUS_ACTIVATED = 'pos.terminal.refund_password_status.activated';
    const STATUS_POS_TERMINAL_REFUND_PASSWORD_STATUS_LOCKED = 'pos.terminal.refund_password_status.locked';
    const STATUS_POS_TERMINAL_REFUND_PASSWORD_STATUS_OPTED_OUT = 'pos.terminal.refund_password_status.opted_out';

    // Fraud
    const STATUS_FRAUD_MERCHANT_NOTIFIED = 'fraud.merchant_notified';

    // Funds split
    const STATUS_FUNDS_SPLIT_CREATED = 'funds_split.created';
    const STATUS_FUNDS_SPLIT_FAILED = 'funds_split.failed';
    const STATUS_FUNDS_SPLIT_RELEASED = 'funds_split.released';
    const STATUS_FUNDS_SPLIT_SETTLED = 'funds_split.settled';

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
