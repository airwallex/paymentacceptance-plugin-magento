<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ConnectionFinalizeResponse;

class ConnectionFinalize extends AbstractApi
{
    /**
     * @var string
     */
    const DEMO_BASE_URL = 'https://demo.airwallex.com/payment_app/plugin/api/v1/';

    /**
     * @var string
     */
    const STAGING_BASE_URL = 'https://staging.airwallex.com/payment_app/plugin/api/v1/';

    /**
     * @var string
     */
    const PRODUCTION_BASE_URL = 'https://www.airwallex.com/payment_app/plugin/api/v1/';

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'connection/finalize';
    }

    /**
     * @param string $platform
     *
     * @return ConnectionFinalize
     */
    public function setPlatform(string $platform): ConnectionFinalize
    {
        return $this->setParam('platform', $platform);
    }

    /**
     * @param string $origin
     *
     * @return ConnectionFinalize
     */
    public function setOrigin(string $origin): ConnectionFinalize
    {
        return $this->setParam('origin', $origin);
    }

    /**
     * @param string $baseUrl
     *
     * @return ConnectionFinalize
     */
    public function setBaseUrl(string $baseUrl): ConnectionFinalize
    {
        return $this->setParam('baseUrl', $baseUrl);
    }

    /**
     * @param string $webhookNotificationUrl
     *
     * @return ConnectionFinalize
     */
    public function setWebhookNotificationUrl(string $webhookNotificationUrl): ConnectionFinalize
    {
        return $this->setParam('webhookNotificationUrl', $webhookNotificationUrl);
    }

    /**
     * @param string $token
     *
     * @return ConnectionFinalize
     */
    public function setAccessToken(string $token): ConnectionFinalize
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * @param string $requestId
     *
     * @return ConnectionFinalize
     */
    public function setRequestId(string $requestId): ConnectionFinalize
    {
        return $this->setParam('requestId', $requestId);
    }

    /**
     * @param string $connectionFinalizeToken
     *
     * @return ConnectionFinalize
     */
    public function setConnectionFinalizeToken(string $connectionFinalizeToken): ConnectionFinalize
    {
        return $this->setParam('token', $connectionFinalizeToken);
    }

    /**
     * @return string
     */
    protected function getToken(): string
    {
        return $this->accessToken ?? '';
    }

    /**
     * @param $response
     *
     * @return ConnectionFinalizeResponse
     */
    protected function parseResponse($response): ConnectionFinalizeResponse
    {
        return new ConnectionFinalizeResponse(json_decode((string)$response->getBody(), true));
    }
}
