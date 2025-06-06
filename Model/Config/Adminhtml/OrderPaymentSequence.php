<?php

namespace Airwallex\Payments\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class OrderPaymentSequence extends Field
{
    protected $_template = 'Airwallex_Payments::config/order_payment_sequence.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }
}
