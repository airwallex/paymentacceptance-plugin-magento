<?php

namespace Airwallex\Payments\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

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

    public function getButtonHtml()
    {
        $data = [
            'id' => 'airwallex_update_settings',
            'label' => __('Connecting'),
        ];

        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData($data)->toHtml();
    }
}
