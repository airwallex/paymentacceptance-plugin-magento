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

class PaymentMethodLogos extends AbstractPaymentMethodField
{
    protected $_template = 'Airwallex_Payments::system/config/payment_method_logos.phtml';

    public function getAvailablePaymentMethods(): array
    {
        if (!$this->availablePaymentMethodsHelper->canInitialize()) {
            return [];
        }

        try {
            $allMethods = $this->availablePaymentMethodsHelper->getAllPaymentMethodTypes();

            return array_filter($allMethods, function($method) {
                // Exclude card payment method from APM logos selection
                if ($method->getName() === 'card') {
                    return false;
                }
                $resources = $method->getResources();
                return !empty($resources) && (!empty($resources['logos']['svg']) || !empty($resources['logos']['png']));
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSelectedPaymentMethodLogos(): array
    {
        $element = $this->getElement();
        $value = $element->getValue();

        if (empty($value)) {
            return [];
        }

        return explode(',', $value);
    }

    public function isLogoSelected(string $methodName): bool
    {
        return in_array($methodName, $this->getSelectedPaymentMethodLogos());
    }
}
