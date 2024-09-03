<?php

namespace Airwallex\Payments\Model\Client;

use Airwallex\Payments\Helper\AuthenticationHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Guzzle\RequestLogger;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Client\Request\Authentication;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Http\Message\ResponseInterface;
use Magento\Checkout\Helper\Data as CheckoutData;

abstract class AbstractClient
{
    public const NOT_FOUND = '404 not found';
    public const METADATA_PAYMENT_METHOD_PREFIX = 'metadata_payment_method_';
    protected const JSON_DECODE_DEPTH = 512;
    protected const SUCCESS_STATUS_START = 200;
    protected const SUCCESS_STATUS_END = 299;
    protected const AUTHENTICATION_FAILED = 401;
    protected const TIME_OUT = 30;
    protected const DEFAULT_HEADER = [
        'Content-Type' => 'application/json',
        'region' => 'string'
    ];

    protected CacheInterface $cache;

    /**
     * @var AuthenticationHelper
     */
    protected AuthenticationHelper $authenticationHelper;

    /**
     * @var IdentityService
     */
    private IdentityService $identityService;

    /**
     * @var RequestLogger
     */
    protected RequestLogger $requestLogger;

    /**
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

    /**
     * @var ModuleListInterface
     */
    protected ModuleListInterface $moduleList;

    /**
     * @var CheckoutData
     */
    protected CheckoutData $checkoutData;

    /**
     * @var array
     */
    private array $params = [];

    /**
     * AbstractClient constructor.
     *
     * @param AuthenticationHelper $authenticationHelper
     * @param IdentityService $identityService
     * @param RequestLogger $requestLogger
     * @param Configuration $configuration
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param CheckoutData $checkoutData
     * @param CacheInterface $cache
     */
    public function __construct(
        AuthenticationHelper     $authenticationHelper,
        IdentityService          $identityService,
        RequestLogger            $requestLogger,
        Configuration            $configuration,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface      $moduleList,
        CheckoutData             $checkoutData,
        CacheInterface           $cache
    )
    {
        $this->authenticationHelper = $authenticationHelper;
        $this->identityService = $identityService;
        $this->requestLogger = $requestLogger;
        $this->configuration = $configuration;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->checkoutData = $checkoutData;
        $this->cache = $cache;
    }

    /**
     * @return mixed
     * @throws GuzzleException
     * @throws JsonException
     * @throws RequestException
     * @throws Exception
     */
    public function send()
    {
        $data = [
            'base_uri' => $this->configuration->getApiUrl(),
            'timeout' => self::TIME_OUT,
        ];
        if ($this->getMethod() !== 'GET') {
            $data['handler'] = $this->requestLogger->getStack();
        }
        $client = new Client($data);

        $request = $this->createRequest($client);
        $statusCode = $request->getStatusCode();

        // If authorization fails on first try, clear token from cache and try again.
        if ($statusCode === self::AUTHENTICATION_FAILED) {
            $this->authenticationHelper->clearToken();
            $request = $this->createRequest($client);
            $statusCode = $request->getStatusCode();
        }

        // If still invalid response, process error.
        if (!($statusCode >= self::SUCCESS_STATUS_START && $statusCode < self::SUCCESS_STATUS_END)) {
            $response = $this->parseJson($request);
            if ($statusCode === 404) {
                throw new RequestException(self::NOT_FOUND);
            }
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
     * @throws JsonException
     */
    protected function parseJson(ResponseInterface $request): object
    {
        return json_decode((string)$request->getBody(), false, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * Get options to create request.
     *
     * @return array
     * @throws GuzzleException
     */
    protected function getRequestOptions(): array
    {
        return [
            'headers' => array_merge(self::DEFAULT_HEADER, $this->getHeaders()),
            'http_errors' => false
        ];
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    protected function getHeaders(): array
    {
        $header = [];

        if ($this instanceof BearerAuthenticationInterface) {
            $header['Authorization'] = 'Bearer ' . $this->authenticationHelper->getBearerToken();
            $header['x-api-version'] = Authentication::X_API_VERSION;
        }

        return $header;
    }

    /**
     * Get information about Magento version executing the request.
     *
     * @return array
     */
    protected function getReferrerData(): array
    {
        return [
            'type' => 'magento',
            'version' => $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version']
        ];
    }

    /**
     * Get information about versions executing the request.
     *
     * @return array
     */
    protected function getMetadata(): array
    {
        $metadata = [
            'php_version' => phpversion(),
            'magento_version' => $this->productMetadata->getVersion(),
            'plugin_version' => $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version'],
            'is_card_active' => $this->configuration->isCardActive() ?? false,
            'is_card_capture_enabled' => $this->configuration->isCardCaptureEnabled() ?? false,
            'is_card_vault_active' => $this->configuration->isCardVaultActive() ?? false,
            'is_express_active' => $this->configuration->isExpressActive() ?? false,
            'is_express_capture_enabled' => $this->configuration->isExpressCaptureEnabled() ?? false,
            'express_display_area' => $this->configuration->expressDisplayArea() ?? '',
            'is_request_logger_enable' => $this->configuration->isRequestLoggerEnable() ?? false,
            'express_checkout' => $this->configuration->getCheckout() ?? '',
            'host' => $_SERVER['HTTP_HOST'] ?? '',
        ];
        if ($methodName = $this->cache->load(self::METADATA_PAYMENT_METHOD_PREFIX . $this->checkoutData->getQuote()->getEntityId())) {
            $metadata['payment_method'] = $methodName;
        }
        return $metadata;
    }

    /**
     * Create request to Airwallex.
     *
     * @param Client $client
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function createRequest(Client $client): ResponseInterface
    {
        $method = $this->getMethod();
        $options = $this->getRequestOptions();

        if ($method === 'POST') {
            $this->params['request_id'] = $this->identityService->generateId();
            $this->params['referrer_data'] = $this->getReferrerData();
            $this->params['metadata'] = $this->getMetadata();
            $options['json'] = $this->params;
        }

        if ($method === 'GET') {
            $options['query'] = $this->params;
        }

        return $client->request($this->getMethod(), $this->getUri(), $options);
    }

    /**
     * @return string
     */
    abstract protected function getUri(): string;

    /**
     * @param ResponseInterface $response
     * @return mixed
     */
    abstract protected function parseResponse(ResponseInterface $response);
}
