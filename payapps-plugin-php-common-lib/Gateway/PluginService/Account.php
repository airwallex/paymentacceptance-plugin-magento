<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Account as StructAccount;
use Exception;

class Account extends AbstractApi
{
    /**
     * @var string
     */
    const DEMO_BASE_URL = 'https://demo.airwallex.com/payment_app/plugin/api/v1/';

    /**
     * @var string
     */
    const PRODUCTION_BASE_URL = 'https://www.airwallex.com/payment_app/plugin/api/v1/';

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'account';
    }

    /**
     * @param $response
     * @return StructAccount
     */
    protected function parseResponse($response): StructAccount
    {
        return new StructAccount(json_decode((string)$response->getBody(), true));
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function send()
    {
        $cacheName = 'awx_account';
        return $this->cacheRemember(
            $cacheName,
            function () {
                return parent::send();
            }
        );
    }
}
