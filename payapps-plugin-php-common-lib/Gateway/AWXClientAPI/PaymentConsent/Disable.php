<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent;

class Disable extends AbstractApi
{
    /**
     * @var string
     */
    private $paymentConsentId;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents/' . $this->paymentConsentId . '/disable';
    }

    /**
     * @param string $paymentConsentId
     *
     * @return $this
     */
    public function setPaymentConsentId(string $paymentConsentId): Disable
    {
        $this->paymentConsentId = $paymentConsentId;

        return $this;
    }

    /**
     * @param $response
     *
     * @return PaymentConsent
     */
    protected function parseResponse($response): PaymentConsent
    {
        return new PaymentConsent(json_decode((string)$response->getBody(), true));
    }
}