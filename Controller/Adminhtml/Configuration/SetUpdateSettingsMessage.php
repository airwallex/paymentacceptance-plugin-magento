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

class SetUpdateSettingsMessage extends Action
{
    public const CACHE_NAME = 'airwallex_update_settings_token';

    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected StoreManager $storeManager;
    protected Random $random;
    protected CacheInterface $cache;
    protected RequestInterface $request;
    protected Configuration $configuration;
    protected IdentityService $identityService;

    public function __construct(
        Context          $context,
        JsonFactory      $resultJsonFactory,
        StoreManager     $storeManager,
        Random           $random,
        CacheInterface   $cache,
        RequestInterface $request,
        Configuration    $configuration,
        IdentityService  $identityService
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
    }

    /**
     * @return Json
     * @throws NoSuchEntityException|LocalizedException
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $environment = 'demo';
        if ($this->request->getParam('env') !== 'demo') {
            $environment = 'www';
        }
        $platform = 'magento';
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $origin = trim($storeUrl, '/');
        $webhookNotificationUrl = $origin . '/airwallex/webhooks';
        if (!function_exists('gzdecode')) {
            return $this->error('Error: The gzdecode function is not available. Please make sure the zlib extension is enabled.', $resultJson);
        }

        $accessToken = gzdecode(base64_decode($this->request->getParam('code')));
        $requestId = $this->identityService->generateId();

        $url = "https://$environment.airwallex.com/payment_app/plugin/api/v1/connection/finalize";
        $data = [
            'platform' => $platform,
            'origin' => $origin,
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
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return $this->error('Error: Unable to fetch the URL. Please try again.', $resultJson);
        }
        $responseData = json_decode($response, true);

        if (!empty($responseData['message']) && $responseData['message'] == 'OK') {
            return $this->success('Your Airwallex plug-in is activated.
            You can also manage which account is connected to your Magento store.', $resultJson);
        }

        return $this->error($responseData['error'], $resultJson);
    }

    public function error($message, $resultJson): Json
    {
        $this->context->getMessageManager()->addErrorMessage($message);
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
