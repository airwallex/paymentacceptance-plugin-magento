<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentMethod;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class Get extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @var string
     */
    private string $paymentMethodId;

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setPaymentMethodId(string $id): self
    {
        $this->paymentMethodId = $id;

        return $this;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_methods/' . $this->paymentMethodId;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     */
    protected function parseResponse(ResponseInterface $response): string
    {
        return $response->getBody();
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }
}
