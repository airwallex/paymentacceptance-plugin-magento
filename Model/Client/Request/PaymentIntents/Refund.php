<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Refund extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @param string $paymentIntentId
     * @param float $amount
     *
     * @return Refund
     */
    public function setInformation(string $paymentIntentId, float $amount): self
    {
        return $this->setParams([
            'amount' => $amount,
            'payment_intent_id' => $paymentIntentId
        ]);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/refunds/create';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return object
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): object
    {
        return $this->parseJson($response);
    }
}
