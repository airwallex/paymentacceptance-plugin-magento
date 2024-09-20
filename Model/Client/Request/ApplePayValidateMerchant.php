<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class ApplePayValidateMerchant extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_session/start';
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'POST';
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

    public function setInitiativeParams($initiative): AbstractClient
    {
        $this->setParams($initiative);
        return $this;
    }
}
