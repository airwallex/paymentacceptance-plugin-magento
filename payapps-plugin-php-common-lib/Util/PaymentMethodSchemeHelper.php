<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Util;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentMethodType\GetList as GetPaymentMethodTypesList;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType as StructPaymentMethodType;

class PaymentMethodSchemeHelper
{
    /**
     * Get card schemes from payment method types API
     *
     * @return array Card schemes organized by payment method and transaction mode
     * @throws \Exception
     */
    public static function getCardSchemesByPaymentMethod(): array
    {
        $schemes = [
            'googlepay' => [
                'oneoff' => [],
                'recurring' => [],
            ],
            'applepay' => [
                'oneoff' => [],
                'recurring' => [],
            ],            
            'card' => [
                'oneoff' => [],
                'recurring' => [],
            ],
        ];

        try {
            $activePaymentMethodTypeItems = (new GetPaymentMethodTypesList())
                ->setCacheTime(1200)
                ->setActive(true)
                ->setIncludeResources(true)
                ->get();
        } catch (\Exception $e) {
            return $schemes;
        }

        if (empty($activePaymentMethodTypeItems)) {
            return $schemes;
        }

        /** @var StructPaymentMethodType $activePaymentMethodTypeItem */
        foreach ($activePaymentMethodTypeItems as $activePaymentMethodTypeItem) {
            $name = $activePaymentMethodTypeItem->getName();
            if ($name === 'googlepay' || $name === 'applepay' || $name === 'card') {
                $mode = $activePaymentMethodTypeItem->getTransactionMode();
                foreach ($activePaymentMethodTypeItem->getCardSchemes() as $scheme) {
                    $schemes[$name][$mode][] = $scheme['name'];
                }
            }
        }

        return $schemes;
    }

    /**
     * Get card schemes for specific transaction mode
     *
     * @param string $transactionMode Transaction mode (oneoff or recurring, default: oneoff)
     * @return array Card schemes for the specified transaction mode
     */
    public static function getCardSchemes(string $transactionMode = StructPaymentMethodType::PAYMENT_METHOD_TYPE_ONE_OFF): array
    {
        $cardSchemesByPaymentMethod = self::getCardSchemesByPaymentMethod();
        return $cardSchemesByPaymentMethod['card'][$transactionMode] ?? [];
    }

    /**
     * Get Apple Pay card schemes for specific transaction mode
     *
     * @param string $transactionMode Transaction mode (oneoff or recurring, default: oneoff)
     * @return array Apple Pay card schemes
     */
    public static function getApplePaySchemes(string $transactionMode = StructPaymentMethodType::PAYMENT_METHOD_TYPE_ONE_OFF): array
    {
        $cardSchemesByPaymentMethod = self::getCardSchemesByPaymentMethod();
        $applePayBrands = $cardSchemesByPaymentMethod['applepay'][$transactionMode] ?? [];

        return self::mapBrandsToApplePayNetworks($applePayBrands);
    }

    /**
     * Get Google Pay card schemes for specific transaction mode
     *
     * @param string $transactionMode Transaction mode (oneoff or recurring, default: oneoff)
     * @return array Google Pay card schemes
     */
    public static function getGooglePaySchemes(string $transactionMode = StructPaymentMethodType::PAYMENT_METHOD_TYPE_ONE_OFF): array
    {
        $cardSchemesByPaymentMethod = self::getCardSchemesByPaymentMethod();
        $googlePayBrands = $cardSchemesByPaymentMethod['googlepay'][$transactionMode] ?? [];

        return self::mapBrandsToGooglePayNetworks($googlePayBrands);
    }

    /**
     * Map brand names to Apple Pay network names
     *
     * @param array $brands Card brands
     * @return array Formatted network names for Apple Pay
     */
    public static function mapBrandsToApplePayNetworks(array $brands): array
    {
        $networks = [];

        foreach ($brands as $brand) {
            $brandLower = strtolower($brand);

            if ($brandLower === 'unionpay') {
                $networks[] = 'chinaUnionPay';
            } elseif ($brandLower === 'mastercard') {
                $networks[] = 'masterCard';
            } elseif ($brandLower !== 'diners') {
                // Filter out diners, keep all other brands as-is (lowercase)
                $networks[] = $brandLower;
            }
        }

        // If masterCard is present but maestro is not, add maestro
        if (in_array('masterCard', $networks) && !in_array('maestro', $networks)) {
            $networks[] = 'maestro';
        }

        return $networks;
    }

    /**
     * Map brand names to Google Pay network names
     *
     * @param array $brands Card brands
     * @return array Formatted network names for Google Pay
     */
    public static function mapBrandsToGooglePayNetworks(array $brands): array
    {
        $networks = [];
        $unsupportedNetworks = ['UNIONPAY', 'MAESTRO', 'DINERS'];

        foreach ($brands as $brand) {
            $brandUpper = strtoupper($brand);

            if (!in_array($brandUpper, $unsupportedNetworks, true)) {
                $networks[] = $brandUpper;
            }
        }

        return $networks;
    }
}
