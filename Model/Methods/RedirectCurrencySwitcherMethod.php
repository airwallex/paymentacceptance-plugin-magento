<?php

namespace Airwallex\Payments\Model\Methods;

use Magento\Quote\Api\Data\CartInterface;

class RedirectCurrencySwitcherMethod extends CardMethod
{
    public function isAvailable(?CartInterface $quote = null): bool
    {
        $codes = $this->availablePaymentMethodsHelper->getLatestItems(false);
        $code = $this->getPaymentMethodCode(static::CODE);
        foreach ($codes as $codeItem) {
            if (!empty($codeItem['name']) && $codeItem['name'] === $code) {
                return true;
            }
        }

        return false;
    }
}
