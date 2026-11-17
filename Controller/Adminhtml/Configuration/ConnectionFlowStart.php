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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Math\Random;

class ConnectionFlowStart extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Config::config';
    public const SESSION_KEY = 'airwallex_connection_state';
    public const STATE_TTL = 900;

    private JsonFactory $resultJsonFactory;
    private Random $random;
    private Validator $formKeyValidator;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Random $random,
        Validator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->random = $random;
        $this->formKeyValidator = $formKeyValidator;
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $result->setHttpResponseCode(403)->setData(['success' => false]);
        }
        $env = $this->getRequest()->getParam('env');
        if (!in_array($env, ['demo', 'prod'], true)) {
            return $result->setHttpResponseCode(400)->setData(['success' => false]);
        }

        $state = $this->random->getRandomString(64);
        $this->_auth->getAuthStorage()->setData(self::SESSION_KEY, [
            'state' => $state,
            'env' => $env,
            'user_id' => (string) $this->_auth->getUser()->getId(),
            'expires_at' => time() + self::STATE_TTL,
        ]);
        return $result->setData(['success' => true, 'state' => $state]);
    }
}
