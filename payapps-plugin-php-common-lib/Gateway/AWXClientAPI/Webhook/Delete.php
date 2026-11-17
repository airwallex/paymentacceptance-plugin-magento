<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Delete extends AbstractApi
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
        return 'webhooks/' . UrlHelper::pathSegment($this->id) . '/delete';
    }

    /**
     * @param string $id
     *
     * @return Delete
     */
    public function setWebhookId(string $id): Delete
    {
        $this->id = $id;
        return $this;
    }

    /**
     * The delete endpoint takes no body, so we do not attach the request_id /
     * referrer_data / metadata that the payment endpoints send.
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
