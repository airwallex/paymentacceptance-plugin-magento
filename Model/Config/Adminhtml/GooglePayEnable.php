<?php

namespace Airwallex\Payments\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GooglePayEnable extends Field
{
    public string $type = 'google';
    protected $_template = 'Airwallex_Payments::config/pay_enable.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }
}
