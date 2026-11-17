<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Logger\Redactor;
use Throwable;
use Exception;

class Log extends AbstractApi
{
    /**
     * @var int
     */
    const TIMEOUT = 3;

    /**
     * @var string
     */
    const SANDBOX_BASE_URL = 'https://o11y.sandbox.airwallex.com/';

    /**
     * @var string
     */
    const PRODUCTION_BASE_URL = 'https://o11y.airwallex.com/';

    /**
     * @var string
     */
    const ON_PROCESS_WEBHOOK_ERROR = 'onProcessWebhookError';

    /**
     * @var string
     */
    const ON_PAYMENT_CREATION_ERROR = 'onPaymentCreationError';

    /**
     * @var string
     */
    const ON_PAYMENT_CONFIRMATION_ERROR = 'onPaymentConfirmationError';

    /**
     * @var string
     */
    private static $sessionId = null;

    /**
     * @var Log
     */
    private static $instance = null;

    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'airtracker/logs';
    }

    /**
     * @param string $token
     *
     * @return string
     */
    protected function decodeJWT(string $token): string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            return 'decode failed';
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!is_array($header) || empty($header['alg']) || !is_string($header['alg'])) {
            return 'decode failed';
        }
        if (strtolower($header['alg']) === 'none') {
            return 'decode failed';
        }
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($payload) || empty($payload['account_id']) || !is_string($payload['account_id'])) {
            return 'decode failed';
        }
        if (!preg_match('/\A[A-Za-z0-9_\-]+\z/', $payload['account_id'])) {
            return 'decode failed';
        }

        return $payload['account_id'];
    }

    /**
     * @param string $data
     *
     * @return string
     */
    private function base64UrlDecode($data)
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($data, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getAccountId(): string
    {
        // The token comes from the authenticated API over TLS; this claim is
        // telemetry only and must never authorize access.
        return $this->decodeJWT($this->getToken());
    }

    /**
     * @return string
     */
    protected static function getSessionId(): string
    {
        if (!isset(self::$sessionId)) {
            self::$sessionId = uniqid('', true);
        }

        return self::$sessionId;
    }

    /**
     * @return string
     */
    protected function getClientPlatform(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $platforms = [
            'linux' => 'Linux',
            'android' => 'Android',
            'windows' => 'Windows',
            'ios' => ['iPhone', 'iPad'],
            'macos' => ['Macintosh', 'Mac OS X'],
        ];

        foreach ($platforms as $key => $values) {
            foreach ((array)$values as $value) {
                if (!empty($userAgent) && strpos($userAgent, $value) !== false) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function initializePostParams()
    {
        $this->setParams([
            'commonData' => [
                'accountId' => $this->getAccountId(),
                'appName' => 'pa_plugin',
                'source' => Init::getInstance()->get('plugin_type') ?: 'php_common_library',
                'deviceId' => 'unknown',
                'sessionId' => self::getSessionId(),
                'appVersion' => Init::getInstance()->get('plugin_version'),
                'platform' => $this->getClientPlatform(),
                'env' => Init::getInstance()->get('env') === 'prod' ? 'prod' : 'sandbox',
            ]
        ]);
    }

    /**
     * @return Log
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Log();
        }
        return self::$instance;
    }

    /**
     * @param string $message
     *
     * @param string $eventName
     * @return mixed
     */
    public static function info(string $message, string $eventName = "")
    {
        return self::log('info', $eventName, $message);
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public static function maskContact(string $input): string
    {
        return Redactor::redact($input);
    }

    /**
     * @param string $message
     *
     * @param string $eventName
     * @return mixed
     */
    public static function error(string $message, string $eventName = "")
    {
        return self::log('error', $eventName, $message);
    }

    /**
     * @param string $severity
     * @param string $eventName
     * @param string $message
     *
     * @return mixed
     */
    public static function log(string $severity, string $eventName, string $message)
    {
        try {
            $instance = self::getInstance();
            $instance->setParams([
                'data' => [
                    [
                        'severity' => $severity,
                        'eventName' => Redactor::redact($eventName),
                        'message' => self::maskContact($message),
                        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                        'metadata' => Redactor::redact($instance->getMetadata()),
                    ]
                ]
            ]);

            return $instance->send();
        } catch (Throwable $e) {
            // Payload preparation must also be best effort.
            return '';
        }
    }

    /**
     * @param $response
     */
    protected function parseResponse($response)
    {
        return $response;
    }

    /**
     * @return mixed
     */
    public function send()
    {
        try {
            return parent::send();
        } catch (Throwable $e) {
        }
        return "";
    }
}
