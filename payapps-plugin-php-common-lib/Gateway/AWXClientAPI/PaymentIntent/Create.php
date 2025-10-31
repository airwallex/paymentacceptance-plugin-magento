<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class Create extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/create';
    }

    /**
     * @param array $additionalInfo
     *
     * @return Create
     */
    public function setAdditionalInfo(array $additionalInfo): Create
    {
        return $this->setParam('additional_info', $additionalInfo);
    }

    /**
     * @param float $amount
     *
     * @return Create
     */
    public function setAmount(float $amount): Create
    {
        return $this->setParam('amount', $amount);
    }

    /**
     * @param string $connectedAccountId
     *
     * @return Create
     */
    public function setConnectedAccountId(string $connectedAccountId): Create
    {
        return $this->setParam('connected_account_id', $connectedAccountId);
    }

    /**
     * @param string $currency
     *
     * @return Create
     */
    public function setCurrency(string $currency): Create
    {
        return $this->setParam('currency', $currency);
    }

    /**
     * @param array $customer
     *
     * @return Create
     */
    public function setCustomer(array $customer): Create
    {
        return $this->setParam('customer', $customer);
    }

    /**
     * @param string $customerId
     *
     * @return Create
     */
    public function setCustomerId(string $customerId): Create
    {
        return $this->setParam('customer_id', $customerId);
    }

    /**
     * @param string $descriptor
     *
     * @return Create
     */
    public function setDescriptor(string $descriptor): Create
    {
        return $this->setParam('descriptor', $descriptor);
    }

    /**
     * @param array $deviceData
     *
     * @return Create
     */
    public function setDeviceData(array $deviceData): Create
    {
        return $this->setParam('device_data', $deviceData);
    }

    /**
     * @param array $externalRecurringData
     *
     * @return Create
     */
    public function setExternalRecurringData(array $externalRecurringData): Create
    {
        return $this->setParam('external_recurring_data', $externalRecurringData);
    }

    /**
     * @param array $fundsSplitData
     *
     * @return Create
     */
    public function setFundsSplitData(array $fundsSplitData): Create
    {
        return $this->setParam('funds_split_data', $fundsSplitData);
    }

    /**
     * @param string $merchantOrderId
     *
     * @return Create
     */
    public function setMerchantOrderId(string $merchantOrderId): Create
    {
        return $this->setParam('merchant_order_id', $merchantOrderId);
    }

    /**
     * @param array $order
     *
     * @return Create
     */
    public function setOrder(array $order): Create
    {
        return $this->setParam('order', $order);
    }

    /**
     * @param array $paymentMethod
     *
     * @return Create
     */
    public function setPaymentMethod(array $paymentMethod): Create
    {
        return $this->setParam('payment_method', $paymentMethod);
    }

    /**
     * @param array $paymentMethodOptions
     *
     * @return Create
     */
    public function setPaymentMethodOptions(array $paymentMethodOptions): Create
    {
        return $this->setParam('payment_method_options', $paymentMethodOptions);
    }

    /**
     * @param string $returnUrl
     *
     * @return Create
     */
    public function setReturnUrl(string $returnUrl): Create
    {
        return $this->setParam('return_url', $returnUrl);
    }

    /**
     * @param array $riskControlOptions
     *
     * @return Create
     */
    public function setRiskControlOptions(array $riskControlOptions): Create
    {
        return $this->setParam('risk_control_options', $riskControlOptions);
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