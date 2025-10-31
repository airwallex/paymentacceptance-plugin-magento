<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Response;
use Error;
use Exception;

abstract class AbstractApi
{
    use CacheTrait;

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
    private $metaData = [];

    /**
     * @return mixed
     * @throws RequestException
     * @throws Exception
     */
    public function send()
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
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => static::TIMEOUT,
        ]);

        $options = [
            'headers' => array_merge($this->getHeaders(), [
                'Content-Type' => 'application/json',
                'x-api-version' => self::X_API_VERSION
            ]),
            'http_errors' => false
        ];

        $method = $this->getMethod();
        if ($method === 'POST') {
            $this->initializePostParams();
            $options['json'] = $this->params;
        } elseif ($method === 'GET') {
            $options['query'] = $this->params;
        }

        $response = $client->request($method, $this->getUri(), $options);
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
        return $this->metaData + [
            'php_version' => phpversion(),
            'platform_version' => Init::getInstance()->get('platform_version'),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
        ];
    }

    /**
     * @param array $data
     * @return self
     */
    public function setMetaData(array $data): self
    {
        $this->metaData = $data;
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
     */
    protected function getToken(): string
    {
        $cacheName = 'awx_api_key_access_token';
        return $this->cacheRemember($cacheName, function () {
            $accessToken = (new Authentication())->send();
            return $accessToken->getToken();
        }, 60 * 30);
    }
}
