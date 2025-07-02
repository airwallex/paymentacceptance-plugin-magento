<?php

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

    /**
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $data = [
            'id' => 'airwallex_update_settings',
            'label' => __('Connect account'),
        ];

        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData($data)->toHtml();
    }
}
