<?php

namespace Airwallex\Payments\Model\Config\Source;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class WebhookUrl extends Field
{
    private const WEBHOOK_PATH = 'airwallex/webhooks';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * WebhookUrl constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritDoc}
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element): string
    {
        $element->setValue($this->scopeConfig->getValue('web/secure/base_url') . self::WEBHOOK_PATH);
        $element->setDisabled('disabled');

        return parent::_getElementHtml($element);
    }
}
