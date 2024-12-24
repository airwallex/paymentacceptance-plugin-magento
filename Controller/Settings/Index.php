<?php

namespace Airwallex\Payments\Controller\Settings;

use Airwallex\Payments\Controller\Adminhtml\Configuration\SetUpdateSettingsMessage;
use Airwallex\Payments\Helper\Configuration;
use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Encryption\EncryptorInterface;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const HTTP_OK = 200;
    private ResponseHttp $response;
    private Writer $configWriter;
    private RequestHttp $request;
    private CacheInterface $cache;
    public Configuration $configuration;
    protected Manager $cacheManager;

    public function __construct(
        ResponseHttp $response,
        Writer $configWriter,
        RequestHttp $request,
        CacheInterface $cache,
        Configuration $configuration,
        Manager $cacheManager
    ) {
        $this->response = $response;
        $this->configWriter = $configWriter;
        $this->request = $request;
        $this->cache = $cache;
        $this->configuration = $configuration;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @return ResponseHttp
     * @throws Exception
     */
    public function execute(): ResponseHttp
    {
        $data = json_decode($this->request->getContent(), true);
        $tokenFromCache =  $this->cache->load(SetUpdateSettingsMessage::CACHE_NAME);
        $this->cache->remove(SetUpdateSettingsMessage::CACHE_NAME);

        $signature = $this->request->getHeader('x-signature');
        if (!$signature) {
            return $this->error('Signature id is required.');
        }
        $ts = $this->request->getHeader('x-timestamp') . $this->request->getContent();
        if (hash_hmac('sha256', $ts, $tokenFromCache) !== $signature) {
            return $this->error('Signature id is invalid.');
        }

        $clientId = $data['client_id'];
        $apiKey = $data['api_key'];
        $webhookKey = $data['webhook_secret'];
        $accountId = $data['account_id'];
        $accountName = $data['account_name'];
        if (empty($clientId)) {
            return $this->error('Client ID is required.');
        }
        if (empty($apiKey)) {
            return $this->error('API Key is required.');
        }
        if (empty($webhookKey)) {
            return $this->error('Webhook Key is required.');
        }
        if (empty($accountId)) {
            return $this->error('Account id is required.');
        }
        if (empty($accountName)) {
            return $this->error('Account name is required.');
        }
        $encryptor = ObjectManager::getInstance()->get(EncryptorInterface::class);
        $mode =  substr($tokenFromCache, 0, 4) === 'demo' ? 'demo' : 'prod';
        $account = $this->configuration->getAccount();
        $arrAccount = $account ? json_decode($account, true) : [];
        $arrAccount[$mode . '_account_id'] = $accountId;
        $arrAccount[$mode . '_account_name'] = $accountName;
        $this->configWriter->save('airwallex/general/' . 'account', json_encode($arrAccount));
        $this->configWriter->save('airwallex/general/' . $mode . '_account_name', $accountName);
        $this->configWriter->save('airwallex/general/' . $mode . '_client_id', $clientId);
        $this->configWriter->save('airwallex/general/' . $mode . '_api_key', $encryptor->encrypt($apiKey));
        $this->configWriter->save('airwallex/general/webhook_' . $mode . '_secret_key', $encryptor->encrypt($webhookKey));
        $this->configWriter->save('airwallex/general/mode', $mode);
        $this->cacheManager->flush(['config']);
        $this->response->setBody(json_encode(['success' => true]));
        return $this->response->setStatusCode(self::HTTP_OK);
    }

    /**
     * @throws Exception
     */
    public function error(string $message)
    {
        throw new Exception($message);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
