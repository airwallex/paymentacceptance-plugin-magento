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

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init as CommonLibraryInit;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Throwable;

/**
 * Info-level payment lifecycle markers. Failures stay inside RemoteLog so a
 * telemetry error cannot break checkout.
 */
class PaymentFlowLog
{
    /**
     * Browser tracking id from the place-order payment payload, when present.
     *
     * @param mixed $paymentMethod
     * @return string
     */
    public static function sessionIdFromPayment($paymentMethod): string
    {
        if (!is_object($paymentMethod) || !method_exists($paymentMethod, 'getAdditionalData')) {
            return '';
        }

        return CheckoutFunnelEvent::sessionIdFromAdditionalData($paymentMethod->getAdditionalData());
    }

    /**
     * Short PA method type for a Magento payment method (and optional express from).
     *
     * @param mixed $paymentMethod
     * @param string $from
     * @return string
     */
    public static function paymentMethodName($paymentMethod, string $from = ''): string
    {
        $code = is_object($paymentMethod) && method_exists($paymentMethod, 'getMethod')
            ? (string) $paymentMethod->getMethod()
            : (string) $paymentMethod;

        return CheckoutFunnelEvent::funnelPaymentMethod($code, $from);
    }

    /**
     * Airtracker event name for every checkout funnel stage.
     */
    public const EVENT_NAME = 'onCheckoutFunnel';

    /**
     * @param array $message
     * @return mixed
     */
    public static function log(array $message)
    {
        try {
            return self::emit(self::EVENT_NAME, (string) json_encode($message));
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Emit an info log to airtracker with commonData.env normalized to "demo"
     * for non-production environments (airtracker rejects "sandbox" with HTTP 400).
     *
     * @param string $eventName
     * @param string $encoded
     * @return mixed
     */
    private static function emit(string $eventName, string $encoded)
    {
        $logger = new class() extends RemoteLog {
            protected function initializePostParams()
            {
                // airtracker/logs rejects env="sandbox" with HTTP 400 ("Invalid env -sandbox")
                // and expects "demo" for non-production environments.
                $env = CommonLibraryInit::getInstance()->get('env') === 'prod' ? 'prod' : 'demo';
                $this->setParams([
                    'commonData' => [
                        'accountId' => $this->getAccountId(),
                        'appName' => 'pa_plugin',
                        'source' => CommonLibraryInit::getInstance()->get('plugin_type') ?: 'php_common_library',
                        'deviceId' => 'unknown',
                        'sessionId' => parent::getSessionId(),
                        'appVersion' => CommonLibraryInit::getInstance()->get('plugin_version'),
                        'platform' => $this->getClientPlatform(),
                        'env' => $env,
                    ],
                ]);
            }

            public function emitInfo(string $eventName, string $encoded)
            {
                $this->setParams([
                    'data' => [
                        [
                            'severity' => 'info',
                            'eventName' => $eventName,
                            'message' => self::maskContact($encoded),
                            'metadata' => $this->getMetadata(),
                        ],
                    ],
                ]);
                return $this->send();
            }
        };

        return $logger->emitInfo($eventName, $encoded);
    }

    /**
     * Log PAYMENT_INTENT_CONFIRMED once per intent id.
     *
     * @param string $sessionId
     * @param string $intentId
     * @param string $paymentMethod
     * @return void
     */
    public static function paymentConfirmed(string $sessionId, string $intentId, string $paymentMethod): void
    {
        try {
            $intentId = CheckoutFunnelEvent::normalizeIntentId($intentId);
            if ($intentId === '') {
                return;
            }
            if ($sessionId === '') {
                $ctx = self::resolveIntentContext(null, $intentId);
                $sessionId = $ctx['session_id'];
                if ($paymentMethod === '') {
                    $paymentMethod = $ctx['payment_method'];
                }
            }

            $cacheKey = 'awx_funnel_confirmed_' . $intentId;
            self::logOnce(
                $cacheKey,
                CheckoutFunnelEvent::buildConfirmMessage($sessionId, $intentId, $paymentMethod)
            );
        } catch (Throwable $e) {
            // Telemetry logging must not interrupt checkout flow.
        }
    }

    /**
     * One CHECKOUT_SUCCEEDED per intent. The return URL, status poll, webhook,
     * and order completion all call this; the cache keeps a later call from
     * logging again after a send that actually went out.
     *
     * @param mixed $quote
     * @param string $intentId
     * @param string $merchantOrderId
     * @return void
     */
    public static function checkoutSucceeded($quote, string $intentId, string $merchantOrderId = ''): void
    {
        try {
            self::emitSucceeded(self::resolveIntentContext($quote, $intentId, $merchantOrderId));
        } catch (Throwable $e) {
            // Telemetry logging must not interrupt checkout flow.
        }
    }

    /**
     * Explicit non-success return from a redirect method. Same session and
     * method resolution as success, without the once-per-intent cache.
     *
     * @param mixed $quote
     * @param string $intentId
     * @param string $errorReason
     * @return void
     */
    public static function checkoutFailed($quote, string $intentId, string $errorReason = ''): void
    {
        try {
            $ctx = self::resolveIntentContext($quote, $intentId);
            self::log(CheckoutFunnelEvent::buildMessage(
                'CHECKOUT_FAILED',
                $ctx['session_id'],
                $ctx['payment_method'],
                false,
                $errorReason,
                [
                    'intent_id' => $ctx['intent_id'],
                    'merchant_order_id' => $ctx['merchant_order_id'],
                ]
            ));
        } catch (Throwable $e) {
            // Telemetry logging must not interrupt checkout flow.
        }
    }

    /**
     * Order reached a paid Magento status. Logged once per intent and status.
     * PAYMENT_INTENT_CONFIRMED and CHECKOUT_SUCCEEDED are emitted first when
     * not yet recorded for the intent.
     *
     * @param mixed $order
     * @param string $intentId
     * @param string $source
     * @return void
     */
    public static function orderStatusUpdated($order, string $intentId, string $source = ''): void
    {
        try {
            if (!is_object($order) || !method_exists($order, 'getStatus')) {
                return;
            }
            $status = (string) $order->getStatus();
            if (!CheckoutFunnelEvent::isPaidOrderStatus($status)) {
                return;
            }

            $ctx = self::resolveIntentContext($order, $intentId);
            self::emitSucceeded($ctx);

            $dedupeId = $ctx['intent_id'] !== '' ? $ctx['intent_id'] : $ctx['merchant_order_id'];
            $cacheKey = $dedupeId !== '' ? 'awx_funnel_order_status_' . $dedupeId . '_' . $status : '';
            self::logOnce($cacheKey, CheckoutFunnelEvent::buildMessage(
                'ORDER_STATUS_UPDATED',
                $ctx['session_id'],
                $ctx['payment_method'],
                false,
                '',
                [
                    'order_status' => $status,
                    'intent_id' => $ctx['intent_id'],
                    'merchant_order_id' => $ctx['merchant_order_id'],
                    'source' => $source,
                ]
            ));
        } catch (Throwable $e) {
            // Telemetry logging must not interrupt checkout flow.
        }
    }

    /**
     * @param array{intent_id: string, session_id: string, payment_method: string, merchant_order_id: string} $ctx
     * @return void
     */
    private static function emitSucceeded(array $ctx): void
    {
        if ($ctx['session_id'] === '') {
            return;
        }

        if ($ctx['intent_id'] !== '') {
            self::paymentConfirmed($ctx['session_id'], $ctx['intent_id'], $ctx['payment_method']);
        }

        $cacheKey = $ctx['intent_id'] !== '' ? 'awx_funnel_succeeded_' . $ctx['intent_id'] : '';
        self::logOnce($cacheKey, CheckoutFunnelEvent::buildMessage(
            'CHECKOUT_SUCCEEDED',
            $ctx['session_id'],
            $ctx['payment_method'],
            false,
            '',
            [
                'intent_id' => $ctx['intent_id'],
                'merchant_order_id' => $ctx['merchant_order_id'],
            ]
        ));
    }

    /**
     * Emit a funnel event at most once per cache key (24h TTL).
     *
     * @param string $cacheKey
     * @param array $message
     * @return void
     */
    private static function logOnce(string $cacheKey, array $message): void
    {
        $cache = null;
        if ($cacheKey !== '') {
            try {
                $cache = ObjectManager::getInstance()->get(CacheInterface::class);
                if ($cache->load($cacheKey)) {
                    return;
                }
            } catch (Throwable $e) {
                $cache = null;
            }
        }

        if (self::log($message) && $cache && $cacheKey !== '') {
            $cache->save('1', $cacheKey, [], 86400);
        }
    }

    /**
     * Resolve session_id, payment_method, and merchant_order_id from the quote
     * or order and a single lookup of the stored intent record.
     *
     * @param mixed $entity
     * @param string $intentId
     * @param string $merchantOrderId
     * @return array{intent_id: string, session_id: string, payment_method: string, merchant_order_id: string}
     */
    private static function resolveIntentContext($entity, string $intentId, string $merchantOrderId = ''): array
    {
        $intentId = CheckoutFunnelEvent::normalizeIntentId($intentId);
        $payment = is_object($entity) && method_exists($entity, 'getPayment') ? $entity->getPayment() : null;
        $method = is_object($payment) && method_exists($payment, 'getMethod')
            ? (string) $payment->getMethod()
            : '';
        $sessionId = self::sessionIdFromPayment($payment);
        if ($merchantOrderId === '' && is_object($entity) && method_exists($entity, 'getIncrementId')) {
            $merchantOrderId = (string) $entity->getIncrementId();
        }

        $from = '';
        if ($intentId !== '') {
            try {
                $record = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getByIntentId($intentId);
                $detail = json_decode((string) $record->getDetail(), true);
                if (is_array($detail)) {
                    if ($sessionId === '') {
                        $sessionId = CheckoutFunnelEvent::sessionIdFromAdditionalData([
                            'awx_session_id' => (string) ($detail['awx_session_id'] ?? ''),
                        ]);
                    }
                    $from = isset($detail['from']) && is_string($detail['from']) ? $detail['from'] : '';
                }
                if ($method === '') {
                    $codes = json_decode((string) $record->getMethodCodes(), true);
                    if (is_array($codes) && !empty($codes)) {
                        $method = (string) end($codes);
                    }
                }
                if ($merchantOrderId === '') {
                    $merchantOrderId = (string) $record->getOrderIncrementId();
                }
            } catch (Throwable $e) {
                // Best-effort lookup from intent record; continue with available context.
            }
        }

        if ($merchantOrderId === '' && is_object($entity) && method_exists($entity, 'getReservedOrderId')) {
            $merchantOrderId = (string) $entity->getReservedOrderId();
        }

        return [
            'intent_id' => $intentId,
            'session_id' => $sessionId,
            'payment_method' => self::paymentMethodName($method, $from),
            'merchant_order_id' => $merchantOrderId,
        ];
    }
}
