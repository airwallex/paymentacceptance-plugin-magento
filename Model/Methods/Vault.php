<?php

namespace Airwallex\Payments\Model\Methods;

use Magento\Vault\Model\VaultPaymentInterface;

class Vault extends CardMethod implements VaultPaymentInterface
{
    public const CODE = 'airwallex_cc_vault';

    public function getProviderCode(): string
    {
        return self::CODE;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function isActive($storeId = null): bool
    {
        return $this->availablePaymentMethodsHelper->configuration->isCardVaultActive();
    }

    public function canUseForCountry($country): bool
    {
        return true;
    }

    public function canUseForCurrency($currencyCode): bool
    {
        return true;
    }

    public function canUseCheckout(): bool
    {
        return true;
    }
}
