<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Log extends AbstractClient implements BearerAuthenticationInterface
{
    public const LOG_URL_LIVE = 'https://api.airwallex.com/';
    public const LOG_URL_SANDBOX = 'https://api-demo.airwallex.com/';

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "POST";
    }

    public function decodeJWT($token)
    {
        if (!strstr($token, '.')) return 'decode failed';
        $str = base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))) ?? '';
        if (!$str) return 'decode failed';
        $arr = json_decode($str, true);
        return $arr['account_id'] ?? 'decode failed';
    }

    /**
     * @throws GuzzleException
     */
    public function setMessage($message, $trace, $intentId = 'unknown')
    {
        return $this->setParams(
            array(
                'commonData' => array(
                    'accountId' => $this->decodeJWT($this->authenticationHelper->getBearerToken()),
                    'appName' => 'pa_plugin',
                    'source' => 'magento',
                    'deviceId' => 'unknown',
                    'sessionId' => $intentId,
                    'appVersion' => $this->productMetadata->getVersion(),
                    'platform' => 'macos',
                    'env' => $this->configuration->isDemoMode() ? 'demo' : 'prod',
                ),
                'data' => array(
                    array(
                        'severity' => 'error',
                        'eventName' => 'magento',
                        'message' => $message,
                        'trace' => $trace,
                        'metadata' => $this->getMetadata()
                    ),
                ),
            )
        );
    }

    protected function getHeaders(): array
    {
        $header = [];

        $header['User-Agent'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

        return $header;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'papluginlogs/logs';
    }

    public function send()
    {
        $domain = $this->configuration->isDemoMode() ? self::LOG_URL_SANDBOX : self::LOG_URL_LIVE;
        $client = new Client([
            'base_uri' => $domain,
            'timeout' => 20,
        ]);

        $request = $this->createRequest($client);

        return $this->parseResponse($request);
    }

    protected function parseResponse(ResponseInterface $response): array
    {
        return [];
    }
}
