<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheManager;
use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\UnauthorizedException;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Response;
use Error;
use Exception;

abstract class AbstractApi
{
    use CacheTrait;

    const ERROR_VALIDATION_ERROR = 'validation_error';
    const ERROR_UNAUTHORIZED = 'unauthorized';
    const ERROR_NOT_FOUND = 'not_found';
    const ERROR_RESOURCE_NOT_FOUND = 'resource_not_found';
    const ERROR_INTERNAL_ERROR = 'internal_error';
    const ACCESS_TOKEN_CACHE_KEY = 'awx_access_token';

    /**
     * @var int
     */
    const TIMEOUT = 30;

    /**
     * @var string
     */
    const DEMO_BASE_URL = 'https://api-demo.airwallex.com/api/v1/';

    /**
     * @var string
     */
    const PRODUCTION_BASE_URL = 'https://api.airwallex.com/api/v1/';

    /**
     * @var string
     */
    const STAGING_BASE_URL = 'https://api-staging.airwallex.com/api/v1/';

    /**
     * @var string
     */
    const X_API_VERSION = '2024-06-30';

    /**
     * @var array
     */
    private $params = [];

    /**
     * @var string
     */
    private $referrerDataType = '';

    /**
     * @var array
     */
    private $metadata = [];

    /**
     * @return mixed
     *
     * @throws RequestException
     * @throws Exception
     */
    public function send()
    {
        try {
            return $this->doSend();
        } catch (UnauthorizedException $e) {
            CacheManager::getInstance()->remove(self::ACCESS_TOKEN_CACHE_KEY);
            return $this->doSend();
        }
    }

    /**
     * @throws RequestException
     * @throws Exception
     */
    public function doSend()
    {
        try {
            if (Init::getInstance()->get('plugin_type') === 'woo_commerce') {
                return $this->wpHttpSend();
            }
            return $this->guzzleHttpSend();
        } catch (Error $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws RequestException
     * @throws Exception
     */
    public function checkResponse(string $body, int $statusCode)
    {
        if ($statusCode >= 400 && $statusCode < 500) {
            if ($statusCode === 401) {
                throw new UnauthorizedException($body);
            }
            throw new RequestException($body);
        }
        if ($body === 'ok') {
            return;
        }
        $responseData = json_decode($body, true);
        if ($responseData === null) {
            throw new Exception('Malformed JSON in response');
        }
    }

    /**
     * @throws Exception
     */
    public function wpHttpSend()
    {
        $url = $this->getBaseUrl() . $this->getUri();
        $headers = array_merge($this->getHeaders(), [
            'Content-Type' => 'application/json',
            'x-api-version' => self::X_API_VERSION
        ]);

        if ($this->getMethod() === 'GET') {
            $data = wp_remote_get($url . '?' . http_build_query($this->params), [
                'timeout' => static::TIMEOUT,
                'headers' => $headers,
            ]);
        } else {
            $this->initializePostParams();
            $data = wp_remote_post($url, [
                'method' => $this->getMethod(),
                'timeout' => static::TIMEOUT,
                'redirection' => 5,
                'headers' => $headers,
                'body' => json_encode($this->params),
                'cookies' => array(),
            ]);
        }
        $response = new Response();
        if (is_wp_error($data)) {
            throw new Exception($data->get_error_message());
        }
        $statusCode = wp_remote_retrieve_response_code($data);
        $body = $data['body'] ?? '';
        $this->checkResponse($body, $statusCode);
        $response->setBody($body);
        return $this->parseResponse($response);
    }

    /**
     * @return mixed
     * @throws RequestException
     * @throws Exception
     */
    public function guzzleHttpSend()
    {
        $isLegacyGuzzle = !method_exists(\GuzzleHttp\Client::class, 'request');

        $clientConfig = [
            $isLegacyGuzzle ? 'base_url' : 'base_uri' => $this->getBaseUrl(),
            'timeout' => static::TIMEOUT,
        ];

        if ($isLegacyGuzzle) {
            $clientConfig['defaults'] = ['timeout' => static::TIMEOUT];
        }

        $client = new \GuzzleHttp\Client($clientConfig);

        $options = [
            'headers' => array_merge($this->getHeaders(), [
                'Content-Type' => 'application/json',
                'x-api-version' => self::X_API_VERSION
            ]),
        ];

        if (!$isLegacyGuzzle) {
            $options['http_errors'] = false;
        } else {
            $options['exceptions'] = false;
        }

        $method = strtoupper($this->getMethod());

        if ($method === 'POST') {
            $this->initializePostParams();
            $options['json'] = $this->params;
        } elseif ($method === 'GET') {
            $options['query'] = $this->params;
        }

        if ($isLegacyGuzzle) {
            $request  = $client->createRequest($method, $this->getUri(), $options);
            $response = $client->send($request);
        } else {
            $response = $client->request($method, $this->getUri(), $options);
        }

        $this->checkResponse((string)$response->getBody(), $response->getStatusCode());
        
        return $this->parseResponse($response);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function initializePostParams()
    {
        $this->params['request_id'] = $this->generateRequestId();
        $this->params['referrer_data'] = $this->getReferrerData();
        $this->params['metadata'] = $this->getMetadata();
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function generateRequestId(): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    /**
     * @param array $params
     * @return self
     */
    protected function setParams(array $params): self
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return self
     */
    protected function setParam(string $name, $value): self
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    protected function unsetParam(string $name): self
    {
        unset($this->params[$name]);
        return $this;
    }

    /**
     * @return array
     */
    protected function getMetadata(): array
    {
        return $this->metadata + [
            'php_version' => phpversion(),
            'platform_version' => Init::getInstance()->get('platform_version'),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
        ];
    }

    /**
     * @param array $data
     * @return self
     */
    public function setMetadata(array $data): self
    {
        $this->metadata = $data;
        return $this;
    }

    /**
     * @return string
     */
    protected function getBaseUrl(): string
    {
        $env = Init::getInstance()->get('env');
        if ($env === 'staging') {
            return static::STAGING_BASE_URL;
        }
        if ($env === 'demo') {
            return static::DEMO_BASE_URL;
        }
        return static::PRODUCTION_BASE_URL;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'POST';
    }

    /**
     * @return array
     */
    private function getReferrerData(): array
    {
        return [
            'type' => $this->referrerDataType ?: Init::getInstance()->get('plugin_type'),
            'version' => Init::getInstance()->get('plugin_version'),
        ];
    }

    /**
     * @param string $type
     * @return self
     */
    public function setReferrerDataType(string $type): self
    {
        $this->referrerDataType = $type;
        return $this;
    }

    /**
     * @return string
     */
    abstract protected function getUri(): string;

    /**
     * @return mixed
     */
    abstract protected function parseResponse($response);

    /**
     * @return array
     * @throws Exception
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getToken(),
        ];
    }

    /**
     * @return string
     * @throws RequestException
     * @throws Exception
     */
    protected function getToken(): string
    {
        return $this->cacheRemember(self::ACCESS_TOKEN_CACHE_KEY, function () {
            $accessToken = (new Authentication())->send();
            return $accessToken->getToken();
        }, 60 * 25);
    }
}
