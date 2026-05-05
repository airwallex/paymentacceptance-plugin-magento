<?php
namespace Airwallex\Payments\Block\Adminhtml\System\Config;

use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\Configuration;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Airwallex\Payments\CommonLibraryInit;

abstract class AbstractPaymentMethodField extends Field
{
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    protected Configuration $configuration;

    public function __construct(
        Context $context,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        Configuration $configuration,
        CommonLibraryInit $commonLibraryInit,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->configuration = $configuration;
        $commonLibraryInit->exec();
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setElement($element);
        return $this->_toHtml();
    }

    public function getElementName(): string
    {
        return $this->getElement()->getName();
    }

    public function getElementId(): string
    {
        return $this->getElement()->getHtmlId();
    }

    public function getMethodLogoUrl($method): ?string
    {
        $resources = $method->getResources();
        if (isset($resources['logos']['svg'])) {
            return $resources['logos']['svg'];
        }

        if (isset($resources['logos']['png'])) {
            return $resources['logos']['png'];
        }

        return null;
    }

    abstract public function getAvailablePaymentMethods(): array;
}
