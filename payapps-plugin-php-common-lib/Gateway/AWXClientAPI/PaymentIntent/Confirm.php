<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class Confirm extends AbstractApi
{
    const ERROR_VALIDATION_ERROR = 'validation_error';
    const ERROR_DUPLICATE_REQUEST = 'duplicate_request';
    const ERROR_SUSPENDED_FROM_ONLINE_PAYMENTS = 'suspended_from_online_payments';
    const ERROR_FREQUENCY_ABOVE_LIMIT = 'frequency_above_limit';
    const ERROR_INVALID_STATUS_FOR_OPERATION = 'invalid_status_for_operation';
    const ERROR_PROVIDER_UNAVAILABLE = 'provider_unavailable';
    const ERROR_CONFIGURATION_ERROR = 'configuration_error';
    const ERROR_AUTHENTICATION_DECLINED = 'authentication_declined';
    const ERROR_RISK_DECLINED = 'risk_declined';
    const ERROR_RESOURCE_ALREADY_EXISTS = 'resource_already_exists';
    const ERROR_PROVIDER_DECLINED = 'provider_declined';
    const ERROR_QUOTE_EXPIRED = 'quote_expired';
    const ERROR_PAYMENT_METHOD_NOT_ALLOWED = 'payment_method_not_allowed';
    const ERROR_CARD_BRAND_NOT_SUPPORTED = 'card_brand_not_supported';
    const ERROR_ISSUER_DECLINED = 'issuer_declined';
    const ERROR_NO_3DS_LIABILITY_SHIFT = 'no_3ds_liability_shift';
    const ERROR_3DS_NOT_SUPPORTED = '3ds_not_supported';
    const ERROR_REJECTED_BY_ROUTING_RULES = 'rejected_by_routing_rules';
    const ERROR_UNAUTHORIZED = 'unauthorized';
    const ERROR_NOT_FOUND = 'not_found';
    const ERROR_RESOURCE_NOT_FOUND = 'resource_not_found';

    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . $this->id . '/confirm';
    }

    /**
     * @param string $customerId
     *
     * @return Confirm
     */
    public function setCustomerId(string $customerId): Confirm
    {
        return $this->setParam('customer_id', $customerId);
    }

    /**
     * @param string $targetCurrency
     * @param string $quoteId
     *
     * @return Confirm
     */
    public function setCurrencySwitcher(string $targetCurrency, string $quoteId): Confirm
    {
        return $this->setParams([
                'currency_switcher' => [
                    'target_currency' => $targetCurrency,
                    'quote_id' => $quoteId
                ],
        ]);
    }

    /**
     * @param array $deviceData
     *
     * @return Confirm
     */
    public function setDeviceData(array $deviceData): Confirm
    {
        return $this->setParam('device_data', $deviceData);
    }

    /**
     * @param string $paymentIntentId
     *
     * @return Confirm
     */
    public function setPaymentIntentId(string $paymentIntentId): Confirm
    {
        $this->id = $paymentIntentId;
        return $this;
    }

    /**
     * @param array $externalRecurringData
     *
     * @return Confirm
     */
    public function setExternalRecurringData(array $externalRecurringData): Confirm
    {
        return $this->setParam('external_recurring_data', $externalRecurringData);
    }

    /**
     * @param array $paymentConsent
     *
     * @return Confirm
     */
    public function setPaymentConsent(array $paymentConsent): Confirm
    {
        return $this->setParam('payment_consent', $paymentConsent);
    }

    /**
     * @param string $paymentConsentId
     *
     * @return Confirm
     */
    public function setPaymentConsentId(string $paymentConsentId): Confirm
    {
        return $this->setParam('payment_consent_id', $paymentConsentId);
    }

    /**
     * @param array $paymentMethod
     *
     * @return Confirm
     */
    public function setPaymentMethod(array $paymentMethod): Confirm
    {
        return $this->setParam('payment_method', $paymentMethod);
    }

    /**
     * @param array $paymentMethodOptions
     *
     * @return Confirm
     */
    public function setPaymentMethodOptions(array $paymentMethodOptions): Confirm
    {
        return $this->setParam('payment_method_options', $paymentMethodOptions);
    }

    /**
     * @param string $returnUrl
     *
     * @return Confirm
     */
    public function setReturnUrl(string $returnUrl): Confirm
    {
        return $this->setParam('return_url', $returnUrl);
    }

    /**
     * @param $response
     *
     * @return PaymentIntent
     */
    protected function parseResponse($response): PaymentIntent
    {
        return new PaymentIntent(json_decode((string)$response->getBody(), true));
    }
}
