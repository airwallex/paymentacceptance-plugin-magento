<?php

namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Traits\HelperTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\PaymentMethodSchemeHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;

class ApmElementOptionsHelper
{
    use HelperTrait;

    private const XML_PATH_ENABLED_METHODS = 'payment/airwallex_payments_apm/enabled_methods';

    private Configuration $configuration;
    private AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    private CustomerSession $customerSession;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Configuration $configuration,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configuration = $configuration;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
    }

    public function getElementOptions(StructPaymentIntent $paymentIntent, $entity = null, ?string $email = null): array
    {
        $elementOptions = [
            'mode' => 'payment',
            'intent_id' => $paymentIntent->getId(),
            'client_secret' => $paymentIntent->getClientSecret(),
            'currency' => $paymentIntent->getCurrency(),
            'methods' => $this->getEnabledPaymentMethods(),
            'autoCapture' => $this->configuration->isAutoCapture('apm'),
        ];

        if ($entity) {
            $billingAddress = $entity->getBillingAddress();
            if ($billingAddress) {
                $billing = $this->getBillingAddress($entity, $this->getEmail($email, $billingAddress));
                $elementOptions['billing'] = $billing;
                $elementOptions['country_code'] = $billing['address']['country_code'] ?? '';

                $shopperName = $this->formatName($billingAddress->getFirstname(), $billingAddress->getLastname());
                if ($shopperName) {
                    $elementOptions['shopper_name'] = $shopperName;
                }

                if ($billingAddress->getTelephone()) {
                    $elementOptions['shopper_phone'] = $billingAddress->getTelephone();
                }

                if (!empty($billing['email'])) {
                    $elementOptions['shopper_email'] = $billing['email'];
                }
            }
        }

        $elementOptions['allowedCardNetworks'] = PaymentMethodSchemeHelper::getCardSchemes();
        $expressCheckoutOptions = $this->getExpressCheckoutRequestOptions();
        $elementOptions['applePayRequestOptions'] = $expressCheckoutOptions;
        $elementOptions['applePayRequestOptions']['supportedNetworks'] = PaymentMethodSchemeHelper::getApplePaySchemes();
        $elementOptions['googlePayRequestOptions'] = $expressCheckoutOptions;
        $elementOptions['googlePayRequestOptions']['allowedCardNetworks'] = PaymentMethodSchemeHelper::getGooglePaySchemes();

        return $elementOptions;
    }

    private function formatName(?string $firstName, ?string $lastName): string
    {
        $first = trim((string)$firstName);
        $last = trim((string)$lastName);

        if ($first && $last) {
            return "$first $last";
        }

        return $first ?: $last;
    }

    private function getEnabledPaymentMethods(): array
    {
        $enabledMethods = $this->scopeConfig->getValue(self::XML_PATH_ENABLED_METHODS);
        if (empty($enabledMethods)) {
            return [];
        }
        $methods = array_filter(array_map('trim', explode(',', $enabledMethods)));
        return array_values(array_filter($methods, function ($method) {
            return $method !== 'card';
        }));
    }

    private function getExpressCheckoutRequestOptions(): array
    {
        $countryCode = $this->configuration->getCountryCode();
        $expressStyle = $this->configuration->getExpressStyle();

        return [
            'countryCode' => strtoupper($countryCode ?: 'US'),
            'buttonColor' => $expressStyle['theme'] ?? 'black',
            'buttonType' => $expressStyle['call_to_action'] ?? 'plain',
        ];
    }

    private function getEmail(?string $email, $address): string
    {
        if (!empty($email)) {
            return (string)$email;
        }

        $addressEmail = $address->getEmail();
        if (!empty($addressEmail)) {
            return (string)$addressEmail;
        }

        try {
            if ($this->customerSession->isLoggedIn()) {
                $customerEmail = $this->customerSession->getCustomer()->getEmail();
                if (!empty($customerEmail)) {
                    return (string)$customerEmail;
                }
            }
        } catch (\Exception $e) {
        }

        return '';
    }
}
