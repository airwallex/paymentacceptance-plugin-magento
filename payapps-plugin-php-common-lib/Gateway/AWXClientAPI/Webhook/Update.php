<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Update extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'webhooks/' . UrlHelper::pathSegment($this->id) . '/update';
    }

    /**
     * @param string $id
     *
     * @return Update
     */
    public function setWebhookId(string $id): Update
    {
        $this->id = $id;
        return $this;
    }

    /**
     * The endpoint where events are sent to. Only updates to url are currently
     * supported by the API.
     *
     * @param string $url
     *
     * @return Update
     */
    public function setUrl(string $url): Update
    {
        return $this->setParam('url', $url);
    }

    /**
     * The update endpoint only accepts url, so we do not attach the
     * request_id / referrer_data / metadata that the payment endpoints send.
     *
     * @return void
     */
    protected function initializePostParams()
    {
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
