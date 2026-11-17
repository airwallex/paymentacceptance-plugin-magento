<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Controller\Settings;

use Airwallex\Payments\Controller\Adminhtml\Configuration\ConnectionFlowRedirectUrl;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Webhook\Webhook;
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
use Magento\Framework\Lock\LockManagerInterface;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const HTTP_OK = 200;
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const LOCK_NAME = 'awx_connection_settings_token';

    private ResponseHttp $response;
    private Writer $configWriter;
    private RequestHttp $request;
    private CacheInterface $cache;
    public Configuration $configuration;
    protected Manager $cacheManager;
    private LockManagerInterface $lockManager;

    public function __construct(
        ResponseHttp $response,
        Writer $configWriter,
        RequestHttp $request,
        CacheInterface $cache,
        Configuration $configuration,
        Manager $cacheManager,
        LockManagerInterface $lockManager
    ) {
        $this->response = $response;
        $this->configWriter = $configWriter;
        $this->request = $request;
        $this->cache = $cache;
        $this->configuration = $configuration;
        $this->cacheManager = $cacheManager;
        $this->lockManager = $lockManager;
    }

    /**
     * @return ResponseHttp
     */
    public function execute(): ResponseHttp
    {
        $rawContent = (string) $this->request->getContent();
        $data = json_decode($rawContent, true);
        if (!is_array($data)) {
            return $this->error('Invalid request payload.');
        }

        $signature = (string) $this->request->getHeader('x-signature');
        if ($signature === '') {
            return $this->error('Signature id is required.', self::HTTP_UNAUTHORIZED);
        }
        $timestamp = (string) $this->request->getHeader('x-timestamp');
        if (!Webhook::isTimestampFresh($timestamp)) {
            return $this->error('Timestamp is invalid or expired.', self::HTTP_UNAUTHORIZED);
        }

        if (!$this->lockManager->lock(self::LOCK_NAME, 5)) {
            return $this->error('Connection request is already being processed.', self::HTTP_UNAUTHORIZED);
        }
        try {
            $tokenFromCache = (string) $this->cache->load(ConnectionFlowRedirectUrl::CACHE_NAME);
            if ($tokenFromCache === '') {
                return $this->error('Can not find token in cache.', self::HTTP_UNAUTHORIZED);
            }
            if (!Webhook::verifySignature($timestamp, $rawContent, $tokenFromCache, $signature)) {
                return $this->error('Signature id is invalid.', self::HTTP_UNAUTHORIZED);
            }

            $clientId = $data['client_id'] ?? null;
            $apiKey = $data['api_key'] ?? null;
            $webhookKey = $data['webhook_secret'] ?? null;
            $accountId = $data['account_id'] ?? null;
            $accountName = $data['account_name'] ?? null;
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
            if (!$this->cache->remove(ConnectionFlowRedirectUrl::CACHE_NAME)) {
                return $this->error('Connection token has already been used.', self::HTTP_UNAUTHORIZED);
            }
            $encryptor = ObjectManager::getInstance()->get(EncryptorInterface::class);
            $mode = substr($tokenFromCache, 0, 4) === 'demo' ? 'demo' : 'prod';
            $account = $this->configuration->getAccount();
            $arrAccount = $account ? json_decode($account, true) : [];
            $arrAccount[$mode . '_account_id'] = $accountId;
            $arrAccount[$mode . '_account_name'] = $accountName;
            $this->configWriter->save('airwallex/general/' . $mode . '_connection_flow', 'success');
            $this->configWriter->save('airwallex/general/' . 'account', json_encode($arrAccount));
            $this->configWriter->save('airwallex/general/' . $mode . '_account_name', $accountName);
            $this->configWriter->save('airwallex/general/' . $mode . '_client_id', $clientId);
            $this->configWriter->save('airwallex/general/' . $mode . '_api_key', $encryptor->encrypt($apiKey));
            $this->configWriter->save('airwallex/general/webhook_' . $mode . '_secret_key', $encryptor->encrypt($webhookKey));
            $this->configWriter->save('airwallex/general/mode', $mode);
            $this->cacheManager->flush(['config']);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }

        $this->response->setBody(json_encode(['success' => true]));
        return $this->response->setStatusCode(self::HTTP_OK);
    }

    public function error(string $message, int $status = self::HTTP_BAD_REQUEST): ResponseHttp
    {
        $this->response->setHeader('Content-Type', 'application/json', true);
        $this->response->setBody(json_encode(['message' => $message]));
        return $this->response->setStatusCode($status);
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
