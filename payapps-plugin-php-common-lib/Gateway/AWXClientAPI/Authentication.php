<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\AccessToken;

class Authentication extends AbstractApi
{
    /**
     * @return array
     */
    protected function getHeaders(): array
    {
        return [
            'x-client-id' => Init::getInstance()->get('client_id'),
            'x-api-key' => Init::getInstance()->get('api_key'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'authentication/login';
    }

    /**
     * @return mixed
     * @throws RequestException
     * @throws Exception
     */
    public function send()
    {
        return $this->doSend();
    }

    /**
     * @param $response
     * @return AccessToken
     */
    protected function parseResponse($response): AccessToken
    {
        return new AccessToken(json_decode((string)$response->getBody(), true));
    }
}
