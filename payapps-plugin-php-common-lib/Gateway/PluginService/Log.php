<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Error;
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
    const DEMO_BASE_URL = 'https://o11y-demo.airwallex.com/';

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
        if (count($parts) < 2) {
            return 'decode failed';
        }

        $decoded = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return $decoded['account_id'] ?? 'decode failed';
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getAccountId(): string
    {
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
                'env' => Init::getInstance()->get('env') === 'demo' ? 'demo' : 'prod',
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
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            list($user, $domain) = explode('@', $input);
            $len = strlen($user);
            if ($len <= 2) {
                $maskedUser = substr($user, 0, 1) . str_repeat('*', $len - 1);
            } else {
                $maskedUser = substr($user, 0, 1) . str_repeat('*', $len - 2) . substr($user, -1);
            }
            return $maskedUser . '@' . $domain;
        } elseif (preg_match('/^\+?\d{6,20}$/', $input)) {
            $prefixLen = 3;
            $suffixLen = 2;
            $len = strlen($input);
            if ($len <= ($prefixLen + $suffixLen)) {
                return substr($input, 0, 1) . str_repeat('*', $len - 1);
            }
            return substr($input, 0, $prefixLen) . str_repeat('*', $len - $prefixLen - $suffixLen) . substr($input, -$suffixLen);
        } else {
            return $input;
        }
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
        $instance = self::getInstance();
        $instance->setParams([
            'data' => [
                [
                    'severity' => $severity,
                    'eventName' => $eventName,
                    'message' => self::maskContact($message),
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                    'metadata' => $instance->getMetadata(),
                ]
            ]
        ]);

        return $instance->send();
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
        } catch (Error $e) {
        } catch (Exception $e) {
        }
        return "";
    }
}
