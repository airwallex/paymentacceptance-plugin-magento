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
namespace Airwallex\Payments\Model\Config\Adminhtml;

use Airwallex\Payments\Controller\Adminhtml\Configuration\ConnectionFlowRedirectUrl;
use Airwallex\Payments\Helper\Configuration;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class UpdateSettings extends Field
{
    protected $_template = 'Airwallex_Payments::config/update_settings.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function getAccount()
    {
        $account = ObjectManager::getInstance()->get(Configuration::class)->getAccount();
        if ($account) {
            return json_decode($account, true);
        }
        return [];
    }

    public function getDemoAccountId() {
        $account = $this->getAccount();
        return $account['demo_account_id'] ?? '';
    }

    public function getDemoAccountName() {
        $account = $this->getAccount();
        return $account['demo_account_name'] ?? '';
    }

    public function getProdAccountId() {
        $account = $this->getAccount();
        return $account['prod_account_id'] ?? '';
    }

    public function getProdAccountName() {
        $account = $this->getAccount();
        return $account['prod_account_name'] ?? '';
    }

    public function getConnectionFlowField(string $env)
    {
        return ObjectManager::getInstance()->get(Configuration::class)->getConnectionFlowField($env);
    }

    public function getConnectionFlowMessage()
    {
        $cache = ObjectManager::getInstance()->get(CacheInterface::class);
        $res = $cache->load(ConnectionFlowRedirectUrl::CONNECTION_FLOW_MESSAGE_CACHE_NAME);
        $cache->remove(ConnectionFlowRedirectUrl::CONNECTION_FLOW_MESSAGE_CACHE_NAME);
        return $res;
    }

    public function getButtonHtml()
    {
        $data = [
            'id' => 'airwallex_update_settings',
            'label' => __('Connect with Airwallex'),
        ];

        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData($data)->toHtml();
    }
}
