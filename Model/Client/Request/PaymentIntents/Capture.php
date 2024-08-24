<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Capture extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @var string
     */
    private string $paymentIntentId;

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setPaymentIntentId(string $id): self
    {
        $this->paymentIntentId = $id;

        return $this;
    }

    /**
     * @param float $amount
     *
     * @return Capture
     */
    public function setInformation(float $amount): self
    {
        return $this->setParams([
            'amount' => $amount,
        ]);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . $this->paymentIntentId . '/capture';
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
