<?php
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
