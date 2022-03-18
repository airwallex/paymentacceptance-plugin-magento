<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Model\Client;

use Airwallex\Payments\Helper\AuthenticationHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Guzzle\RequestLogger;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\DataObject\IdentityService;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractClient
{
    private const JSON_DECODE_DEPTH = 512;
    private const SUCCESS_STATUS_START = 200;
    private const SUCCESS_STATUS_END = 299;
    private const TIME_OUT = 30;
    private const DEFAULT_HEADER = [
        'Content-Type' => 'application/json',
        'region' => 'string'
    ];

    /**
     * @var AuthenticationHelper
     */
    private $authenticationHelper;

    /**
     * @var IdentityService
     */
    private $identityService;

    /**
     * @var RequestLogger
     */
    private $requestLogger;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var array
     */
    private $params = [];

    /**
     * AbstractClient constructor.
     *
     * @param AuthenticationHelper $authenticationHelper
     * @param IdentityService $identityService
     * @param RequestLogger $requestLogger
     * @param Configuration $configuration
     */
    public function __construct(
        AuthenticationHelper $authenticationHelper,
        IdentityService $identityService,
        RequestLogger $requestLogger,
        Configuration $configuration
    ) {
        $this->authenticationHelper = $authenticationHelper;
        $this->identityService = $identityService;
        $this->requestLogger = $requestLogger;
        $this->configuration = $configuration;
    }

    /**
     * @return mixed
     * @throws GuzzleException
     * @throws \JsonException
     * @throws RequestException
     */
    public function send()
    {
        $client = new Client([
            'base_uri' => $this->configuration->getApiUrl(),
            'timeout' => self::TIME_OUT,
            'handler' => $this->requestLogger->getStack()
        ]);

        $method = $this->getMethod();

        $options = [
            'headers' => array_merge(self::DEFAULT_HEADER, $this->getHeaders()),
            'http_errors' => false
        ];

        if ($method === 'POST') {
            $this->params['request_id'] = $this->identityService->generateId();
            $options['json'] = $this->params;
        }

        $request = $client->request($this->getMethod(), $this->getUri(), $options);
        $statusCode = $request->getStatusCode();

        if (!($statusCode >= self::SUCCESS_STATUS_START && $statusCode < self::SUCCESS_STATUS_END)) {
            $response = $this->parseJson($request);
            throw new RequestException($response->message);
        }

        return $this->parseResponse($request);
    }

    /**
     * @param array $params
     *
     * @return $this
     */
    protected function setParams(array $params): AbstractClient
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    protected function setParam(string $name, string $value): AbstractClient
    {
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'POST';
    }

    /**
     * @param ResponseInterface $request
     *
     * @return object
     * @throws \JsonException
     */
    protected function parseJson(ResponseInterface $request): object
    {
        return json_decode((string) $request->getBody(), false, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        $header = [];

        if ($this instanceof BearerAuthenticationInterface) {
            $header['Authorization'] = 'Bearer ' . $this->authenticationHelper->getBearerToken();
        }

        return $header;
    }

    /**
     * @return string
     */
    abstract protected function getUri(): string;

    /**
     * @param ResponseInterface $request
     *
     * @return mixed
     */
    abstract protected function parseResponse(ResponseInterface $request);
}
