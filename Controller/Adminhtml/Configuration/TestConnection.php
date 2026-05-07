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

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Authentication;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\Configuration;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class TestConnection extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected RequestInterface $request;
    protected ScopeConfigInterface $scopeConfig;
    protected Configuration $configuration;
    protected CommonLibraryInit $commonLibraryInit;
    protected FormKeyValidator $formKeyValidator;

    public function __construct(
        Context              $context,
        JsonFactory          $resultJsonFactory,
        RequestInterface     $request,
        ScopeConfigInterface $scopeConfig,
        Configuration        $configuration,
        CommonLibraryInit    $commonLibraryInit,
        FormKeyValidator     $formKeyValidator
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->configuration = $configuration;
        $this->commonLibraryInit = $commonLibraryInit;
        $this->formKeyValidator = $formKeyValidator;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Config::config');
    }

    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid form key. Please refresh the page and try again.'
            ]);
        }

        $env = $this->request->getParam('env');

        if (!in_array($env, ['demo', 'prod'], true)) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid environment parameter.'
            ]);
        }

        $clientId = $this->request->getParam('client_id');
        $apiKey = $this->request->getParam('api_key');

        if (is_string($apiKey) && $apiKey !== '' && preg_match('/^\*+$/', $apiKey)) {
            $configPath = 'airwallex/general/' . $env . '_api_key';
            $apiKey = $this->scopeConfig->getValue($configPath);
        }

        if (is_string($clientId) && $clientId !== '' && preg_match('/^\*+$/', $clientId)) {
            $configPath = 'airwallex/general/' . $env . '_client_id';
            $clientId = $this->scopeConfig->getValue($configPath);
        }

        if (!$clientId || !$apiKey) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Client ID and API Key are required.'
            ]);
        }

        // Save original configuration to restore after test
        $originalEnv = $this->configuration->getMode();
        $originalClientId = $this->configuration->getClientId();
        $originalApiKey = $this->configuration->getApiKey();

        try {
            // Temporarily update CommonLibrary config with test credentials
            Init::getInstance()->updateConfig([
                'env' => $env,
                'client_id' => $clientId,
                'api_key' => $apiKey,
            ]);

            $accessToken = (new Authentication())->send();

            if ($accessToken && $accessToken->getToken()) {
                return $resultJson->setData([
                    'success' => true,
                    'message' => 'Connection successful!'
                ]);
            }

            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid credentials. Please check your Client ID and API Key.'
            ]);

        } catch (Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid credentials. Please check your Client ID and API Key.'
            ]);
        } finally {
            Init::getInstance()->updateConfig([
                'env' => $originalEnv,
                'client_id' => $originalClientId,
                'api_key' => $originalApiKey,
            ]);
        }
    }
}
