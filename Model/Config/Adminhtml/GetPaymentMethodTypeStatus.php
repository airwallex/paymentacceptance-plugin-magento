<?php

namespace Airwallex\Payments\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GetPaymentMethodTypeStatus extends Field
{
    public string $type = 'pay_enable';
    protected $_template = 'Airwallex_Payments::config/get_payment_method_type_status.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }
}
