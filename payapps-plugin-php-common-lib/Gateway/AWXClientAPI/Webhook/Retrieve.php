<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Retrieve extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
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
        return 'webhooks/' . UrlHelper::pathSegment($this->id);
    }

    /**
     * @param string $id
     *
     * @return Retrieve
     */
    public function setWebhookId(string $id): Retrieve
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param $response
     *
     * @return Webhook
     */
    protected function parseResponse($response): Webhook
    {
        // Guard against a null/non-array json_decode result (empty or invalid body).
        return new Webhook(json_decode((string)$response->getBody(), true) ?: []);
    }
}
