<?php
namespace Airwallex\Payments\Block\Adminhtml\System\Config;

class EnabledPaymentMethods extends AbstractPaymentMethodField
{
    protected $_template = 'Airwallex_Payments::system/config/enabled_payment_methods.phtml';

    public function getAvailablePaymentMethods(): array
    {
        if (!$this->availablePaymentMethodsHelper->canInitialize()) {
            return [];
        }

        try {
            $allMethods = $this->availablePaymentMethodsHelper->getAllPaymentMethodTypes();

            // Exclude card payment method from APM enabled methods selection
            return array_filter($allMethods, function($method) {
                return $method->getName() !== 'card';
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getEnabledPaymentMethods(): array
    {
        $element = $this->getElement();
        $value = $element->getValue();

        if (empty($value)) {
            return [];
        }

        return explode(',', $value);
    }

    public function isMethodEnabled(string $methodName): bool
    {
        return in_array($methodName, $this->getEnabledPaymentMethods());
    }
}
