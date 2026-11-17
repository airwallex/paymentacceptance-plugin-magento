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
namespace Airwallex\Payments\Controller\Adminhtml\Configuration;

use Airwallex\Payments\Helper\Configuration;
use Exception;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\Cache\Manager;

class ConnectionFlowRedirectUrl extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';
    public const CACHE_NAME = 'airwallex_update_settings_token';
    public const CONNECTION_FLOW_MESSAGE_CACHE_NAME = 'airwallex_connection_flow_message';
    public const SETTINGS_TOKEN_TTL = 900;
    public const MAX_CONNECTION_CODE_LENGTH = 65536;

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

    public function resolveSafeTargetUrl($encodedTargetUrl): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        if (!is_string($encodedTargetUrl) || $encodedTargetUrl === '') {
            return $baseUrl;
        }

        $decoded = base64_decode($encodedTargetUrl, true);
        if ($decoded === false) {
            return $baseUrl;
        }

        // Browsers normalize backslashes before parsing the host; PHP does not.
        if (strpos($decoded, '\\') !== false || preg_match('/[\x00-\x20\x7f]/', $decoded)) {
            return $baseUrl;
        }

        $parsed = parse_url($decoded);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return $baseUrl;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])
            || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)
        ) {
            return $baseUrl;
        }

        $base = parse_url($baseUrl);
        $targetPort = $parsed['port'] ?? (strtolower($parsed['scheme']) === 'https' ? 443 : 80);
        $basePort = $base['port'] ?? (strtolower($base['scheme'] ?? '') === 'https' ? 443 : 80);
        if (empty($base['scheme']) || empty($base['host']) || strcasecmp($parsed['host'], $base['host']) !== 0
            || strcasecmp($parsed['scheme'], $base['scheme']) !== 0 || $targetPort !== $basePort
        ) {
            return $baseUrl;
        }

        return $decoded;
    }

    private function normalizedEnv(): string
    {
        return $this->request->getParam('env') === 'demo' ? 'demo' : 'prod';
    }

    public function connection_failed()
    {
        $this->configWriter->save('airwallex/general/' . $this->normalizedEnv() . '_connection_flow', 'connection_failed');
        $this->cacheManager->flush(['config']);
    }

    /**
     * @return Redirect
     * @throws NoSuchEntityException|LocalizedException
     */
    public function execute(): Redirect
    {
        $session = $this->_auth->getAuthStorage();
        $pending = $session->getData(ConnectionFlowStart::SESSION_KEY);
        $state = $this->request->getParam('state');
        if (!is_array($pending) || !is_string($state) || $state === ''
            || !isset($pending['state'], $pending['env'], $pending['user_id'], $pending['expires_at'])
            || !hash_equals($pending['state'], $state)
            || $pending['env'] !== $this->request->getParam('env')
            || $pending['user_id'] !== (string) $this->_auth->getUser()->getId()
            || $pending['expires_at'] <= time()
        ) {
            $this->context->getMessageManager()->addErrorMessage(
                __('The Airwallex connection request is invalid or expired. Please start again from the payment settings.')
            );
            return $this->resultRedirectFactory->create()->setPath(
                'adminhtml/system_config/edit',
                ['section' => 'payment']
            );
        }
        // Consume before processing either a success or error callback, including failed finalize requests.
        $session->unsetData(ConnectionFlowStart::SESSION_KEY);

        if ($this->request->getParam('error') === 'connection_failed') {
            $this->connection_failed();
            return $this->error('We were unable to connect the Airwallex account as the business information of the account does not match this Magento store. You can still connect the account using its unique client ID and API key, or connect a different account.');
        }
        if (empty($this->request->getParam('code'))) {
            return $this->resultRedirectFactory->create()->setUrl(
                $this->resolveSafeTargetUrl($this->request->getParam('target_url'))
            );
        }
        // The token prefix stays "demo" so the connection callback
        // (Controller\Settings\Index) can still resolve the stored sandbox mode,
        // but the finalize request must target the sandbox domain.
        $environment = 'demo';
        $host = 'www.sandbox.airwallex.com';
        if ($this->normalizedEnv() !== 'demo') {
            $environment = 'www';
            $host = 'www.airwallex.com';
        }
        $platform = 'magento';
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $baseUrl = trim($storeUrl, '/');
        $webhookNotificationUrl = $baseUrl . '/airwallex/webhooks';
        $code = (string) $this->request->getParam('code');
        if (strlen($code) > self::MAX_CONNECTION_CODE_LENGTH) {
            $this->connection_failed();
            return $this->error('Error: The connection code is invalid.');
        }

        if (!function_exists('gzdecode')) {
            return $this->error('Error: The gzdecode function is not available. Please make sure the zlib extension is enabled.');
        }

        $decoded = base64_decode($code, true);
        $accessToken = $decoded === false ? false : @gzdecode($decoded);
        if (!is_string($accessToken) || $accessToken === '' || preg_match('/[\x00-\x1F\x7F]/', $accessToken)) {
            $this->connection_failed();
            return $this->error('Error: The connection code is invalid.');
        }
        $requestId = $this->identityService->generateId();

        $url = "https://$host/payment_app/plugin/api/v1/connection/finalize";
        $data = [
            'platform' => $platform,
            'origin' => $this->getOriginFromUrl($baseUrl),
            'baseUrl' => $baseUrl,
            'webhookNotificationUrl' => $webhookNotificationUrl,
            'token' => $this->token($environment),
            'requestId' => $requestId
        ];

        if (!function_exists('curl_init')) {
            return $this->error('Error: Please make sure the curl extension is enabled.');
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($response === false && $curlError !== '') {
                throw new Exception($curlError);
            }
        } catch (Exception $e) {
            $this->connection_failed();
            return $this->error('Error: ' . $e->getMessage());
        }
        if ($response === false) {
            $this->connection_failed();
            return $this->error('Error: Unable to fetch the URL. Please try again.');
        }
        $responseData = json_decode($response, true);

        if (!empty($responseData['message']) && $responseData['message'] == 'OK') {
            return $this->success('Your Airwallex plug-in is activated.
            You can also manage which account is connected to your Magento store.');
        }

        $this->connection_failed();
        return $this->error($responseData['error'] ?? 'Connection failed.');
    }

    public function error($message): Redirect
    {
        $this->cache->save(json_encode([
            'type' => 'error',
            'message' => $message,
            'env' => $this->normalizedEnv(),
        ]), self::CONNECTION_FLOW_MESSAGE_CACHE_NAME, [], 60 * 60 * 24);
        return $this->resultRedirectFactory->create()->setUrl(
            $this->resolveSafeTargetUrl($this->request->getParam('target_url'))
        );
    }

    public function success($message): Redirect
    {
        $this->context->getMessageManager()->addSuccessMessage($message);
        return $this->resultRedirectFactory->create()->setUrl(
            $this->resolveSafeTargetUrl($this->request->getParam('target_url'))
        );
    }

    /**
     * @throws LocalizedException
     */
    public function token($environment): string
    {
        $token = $environment . '-' . $this->random->getRandomString(32);
        $this->cache->save($token, self::CACHE_NAME, [], self::SETTINGS_TOKEN_TTL);
        return $token;
    }
}
