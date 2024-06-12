<?php

namespace Airwallex\Payments\Model\Methods;

use Magento\Vault\Model\VaultPaymentInterface;

class Vault extends CardMethod implements VaultPaymentInterface
{
    public const CODE = 'airwallex_cc_vault';

    public function getProviderCode()
    {
        return self::CODE;
    }

    public function getCode()
    {
        return self::CODE;
    }
    
    public function isActive($storeId = null)
    {
        return $this->availablePaymentMethodsHelper->configuration->isCardVaultActive();
    }

    public function canUseForCountry($country)
    {
        return true;
    }

    public function canUseForCurrency($currencyCode)
    {
        return true;
    }

    public function canUseCheckout()
    {
        return true;
    }
}
