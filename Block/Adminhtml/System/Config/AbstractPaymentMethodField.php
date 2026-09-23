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
