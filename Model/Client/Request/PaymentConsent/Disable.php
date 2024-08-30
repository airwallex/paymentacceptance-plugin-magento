<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentConsent;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Http\Message\ResponseInterface;

class Disable extends AbstractClient implements BearerAuthenticationInterface
{
    protected ?string $paymentConsentId = null;

    /**
     * @param string $paymentConsentId
     * @return $this
     */
    public function setPaymentConsentId(string $paymentConsentId): Disable
    {
        $this->paymentConsentId = $paymentConsentId;

        return $this;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents/' . $this->paymentConsentId . '/disable';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     * @throws JsonException
     * @throws LocalizedException
     */
    protected function parseResponse(ResponseInterface $response): string
    {
        if ($this->paymentConsentId === null) {
            throw new LocalizedException(__('Payment Consent ID not set'));
        }

        $response = $this->parseJson($response);

        return $response->status === 'DISABLED';
    }
}
