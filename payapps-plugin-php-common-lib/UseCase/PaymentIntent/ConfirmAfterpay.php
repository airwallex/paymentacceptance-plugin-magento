<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Confirm as PaymentIntentConfirm;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent;
use Exception;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;

class ConfirmAfterpay
{
    /**
     * @var string
     */
    private $paymentIntentId;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var mixed
     */
    private $deviceData;

    /**
     * @var string
     */
    private $triggeredBy;

    /**
     * @var string
     */
    private $merchantTriggerReason;

    /**
     * @var string
     */
    private $paymentConsentId;

    /**
     * @var string
     */
    private $shopperEmail;

    /**
     * @var array
     */
    private $billing;

    /**
     * @var bool
     */
    private $isAutoCapture = true;

    /**
     * @var string
     */
    private $returnUrl;

    /**
     * @param string $paymentIntentId
     * @return ConfirmAfterpay
     */
    public function setPaymentIntentId(string $paymentIntentId): ConfirmAfterpay
    {
        $this->paymentIntentId = $paymentIntentId;
        return $this;
    }

    /**
     * @param string $customerId
     * @return ConfirmAfterpay
     */
    public function setCustomerId(string $customerId): ConfirmAfterpay
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * @param array $deviceData
     * @return ConfirmAfterpay
     */
    public function setDeviceData(array $deviceData): ConfirmAfterpay
    {
        $this->deviceData = $deviceData;
        return $this;
    }

    /**
     * @param string $triggeredBy
     * @return ConfirmAfterpay
     */
    public function setTriggeredBy(string $triggeredBy): ConfirmAfterpay
    {
        $this->triggeredBy = $triggeredBy;
        return $this;
    }

    /**
     * @param string $merchantTriggerReason
     * @return ConfirmAfterpay
     */
    public function setMerchantTriggerReason(string $merchantTriggerReason): ConfirmAfterpay
    {
        $this->merchantTriggerReason = $merchantTriggerReason;
        return $this;
    }

    /**
     * @param string $paymentConsentId
     * @return ConfirmAfterpay
     */
    public function setPaymentConsentId(string $paymentConsentId): ConfirmAfterpay
    {
        $this->paymentConsentId = $paymentConsentId;
        return $this;
    }

    /**
     * @param string $shopperEmail
     * @return ConfirmAfterpay
     */
    public function setShopperEmail(string $shopperEmail): ConfirmAfterpay
    {
        $this->shopperEmail = $shopperEmail;
        return $this;
    }

    /**
     * @param array $billing
     * @return ConfirmAfterpay
     */
    public function setBilling(array $billing): ConfirmAfterpay
    {
        $this->billing = $billing;
        return $this;
    }

    /**
     * @param bool $isAutoCapture
     * @return ConfirmAfterpay
     */
    public function setIsAutoCapture(bool $isAutoCapture): ConfirmAfterpay
    {
        $this->isAutoCapture = $isAutoCapture;
        return $this;
    }

    /**
     * @param string $returnUrl
     * @return ConfirmAfterpay
     */
    public function setReturnUrl(string $returnUrl): ConfirmAfterpay
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function send(): StructPaymentIntent
    {
        $request = (new PaymentIntentConfirm());
        if ($this->customerId) {
            $request->setCustomerId($this->customerId);
        }
        if ($this->triggeredBy && $this->merchantTriggerReason) {
            if (in_array($this->triggeredBy, [PaymentConsent::TRIGGERED_BY_CUSTOMER, PaymentConsent::TRIGGERED_BY_MERCHANT], true)) {
                $request->setPaymentConsent([
                    'triggered_by' => $this->triggeredBy,
                    'merchant_trigger_reason' => $this->merchantTriggerReason,
                ]);
            }
        }
        if ($this->deviceData) {
            $request->setDeviceData($this->deviceData);
        }
        if ($this->paymentConsentId) {
            $request->setPaymentConsentId($this->paymentConsentId);
        }
        if ($this->shopperEmail) {
            $data = [
                'shopper_email' => $this->shopperEmail,
            ];
            if ($this->billing) {
                $data['billing'] = $this->billing;
            }
            $request->setPaymentMethod([
                'afterpay' => $data,
            ]);
        }
        if ($this->isAutoCapture) {
            $request->setPaymentMethodOptions([
                'afterpay' => [
                    'auto_capture' => $this->isAutoCapture,
                ],
            ]);
        }
        if ($this->returnUrl) {
            $request->setReturnUrl($this->returnUrl);
        }
        return $request->send();
    }
}