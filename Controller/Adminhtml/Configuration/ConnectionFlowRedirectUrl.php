<?php

namespace Airwallex\Payments\Controller\Adminhtml\Configuration;

use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\Cache\Manager;

class ConnectionFlowRedirectUrl extends Action
{
    public const CACHE_NAME = 'airwallex_update_settings_token';
    public const CONNECTION_FLOW_MESSAGE_CACHE_NAME = 'airwallex_connection_flow_message';

    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected StoreManager $storeManager;
    protected Random $random;
    protected CacheInterface $cache;
    protected RequestInterface $request;
    protected Configuration $configuration;
    protected Writer $configWriter;
    protected IdentityService $identityService;
    protected Manager $cacheManager;

    public function __construct(
        Context          $context,
        JsonFactory      $resultJsonFactory,
        StoreManager     $storeManager,
        Random           $random,
        CacheInterface   $cache,
        RequestInterface $request,
        Configuration    $configuration,
        IdentityService  $identityService,
        Manager          $cacheManager,
        Writer           $configWriter
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->random = $random;
        $this->cache = $cache;
        $this->request = $request;
        $this->configuration = $configuration;
        $this->identityService = $identityService;
        $this->cacheManager = $cacheManager;
        $this->configWriter = $configWriter;
    }

    public function getOriginFromUrl($url): string
    {
        $parsedUrl = parse_url($url);
        $origin = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $origin .= ':' . $parsedUrl['port'];
        }
        return $origin;
    }

    public function connection_failed()
    {
        $this->configWriter->save('airwallex/general/' . $this->request->getParam('env') . '_connection_flow', 'connection_failed');
        $this->cacheManager->flush(['config']);
    }

    /**
     * @return Json
     * @throws NoSuchEntityException|LocalizedException
     */
    public function execute(): Json
    {
        if ($this->request->getParam('error') === 'connection_failed') {
            $this->connection_failed();
            return $this->error('We were unable to connect the Airwallex account as the business information of the account does not match this Magento store. You can still connect the account using its unique client ID and API key, or connect a different account.', $this->resultJsonFactory->create());
        }
        $resultJson = $this->resultJsonFactory->create();
        if (empty($this->request->getParam('code'))) {
            header('Location: ' . base64_decode($this->request->getParam('target_url')));
            return $resultJson;
        }
        $environment = 'demo';
        if ($this->request->getParam('env') !== 'demo') {
            $environment = 'www';
        }
        $platform = 'magento';
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $baseUrl = trim($storeUrl, '/');
        $webhookNotificationUrl = $baseUrl . '/airwallex/webhooks';
        if (!function_exists('gzdecode')) {
            return $this->error('Error: The gzdecode function is not available. Please make sure the zlib extension is enabled.', $resultJson);
        }

        $accessToken = gzdecode(base64_decode($this->request->getParam('code')));
        $requestId = $this->identityService->generateId();

        $url = "https://$environment.airwallex.com/payment_app/plugin/api/v1/connection/finalize";
        $data = [
            'platform' => $platform,
            'origin' => $this->getOriginFromUrl($baseUrl),
            'baseUrl' => $baseUrl,
            'webhookNotificationUrl' => $webhookNotificationUrl,
            'token' => $this->token($environment),
            'requestId' => $requestId
        ];

        if (!function_exists('curl_init')) {
            return $this->error('Error: Please make sure the curl extension is enabled.', $resultJson);
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ],
                'content' => json_encode($data),
                'ignore_errors' => true
            ],
        ];

        $context = stream_context_create($options);
        try {
            $response = file_get_contents($url, false, $context);
        } catch (\Exception $e) {
            $this->connection_failed();
            return $this->error('Error: ' . $e->getMessage(), $resultJson);
        }
        if ($response === false) {
            $this->connection_failed();
            return $this->error('Error: Unable to fetch the URL. Please try again.', $resultJson);
        }
        $responseData = json_decode($response, true);

        if (!empty($responseData['message']) && $responseData['message'] == 'OK') {
            return $this->success('Your Airwallex plug-in is activated.
            You can also manage which account is connected to your Magento store.', $resultJson);
        }

        $this->connection_failed();
        return $this->error($responseData['error'], $resultJson);
    }

    public function error($message, $resultJson): Json
    {
        $this->cache->save(json_encode([
            'type' => 'error',
            'message' => $message,
            'env' => $this->request->getParam('env'),
        ]), self::CONNECTION_FLOW_MESSAGE_CACHE_NAME, [], 60 * 60 * 24);
        header('Location: ' . base64_decode($this->request->getParam('target_url')));
        return $resultJson;
    }

    public function success($message, $resultJson): Json
    {
        $this->context->getMessageManager()->addSuccessMessage($message);
        header('Location: ' . base64_decode($this->request->getParam('target_url')));
        return $resultJson;
    }

    /**
     * @throws LocalizedException
     */
    public function token($environment): string
    {
        $token = $environment . '-' . $this->random->getRandomString(32);
        $this->cache->save($token, self::CACHE_NAME, [], 60 * 60 * 24);
        return $token;
    }
}
