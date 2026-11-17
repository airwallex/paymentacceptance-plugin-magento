<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;

class Create extends AbstractApi
{
    /**
     * Caller-supplied idempotency key. When set, it is sent as the request_id
     * so the Airwallex API deduplicates a retried/duplicate webhook
     * registration server-side instead of creating a second webhook.
     *
     * @var string|null
     */
    private $requestId = null;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'webhooks/create';
    }

    /**
     * The endpoint where events are sent to.
     *
     * @param string $url
     *
     * @return Create
     */
    public function setUrl(string $url): Create
    {
        return $this->setParam('url', $url);
    }

    /**
     * The API version which controls the event payload structure, in
     * YYYY-MM-DD format, e.g. 2022-11-11.
     *
     * @param string $version
     *
     * @return Create
     */
    public function setVersion(string $version): Create
    {
        return $this->setParam('version', $version);
    }

    /**
     * A list of events subscribed by this webhook. Events cannot be modified
     * after creation.
     *
     * @param array $events
     *
     * @return Create
     */
    public function setEvents(array $events): Create
    {
        return $this->setParam('events', array_values($events));
    }

    /**
     * Set a deterministic idempotency key for this webhook registration.
     * Passing the same request_id for a logically identical registration lets
     * the Airwallex API deduplicate it server-side, preventing a duplicate
     * webhook on retry or re-connection.
     *
     * @param string $requestId
     *
     * @return Create
     */
    public function setRequestId(string $requestId): Create
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * The webhook management API only accepts events, request_id, url and
     * version, so we do not attach the referrer_data / metadata that the
     * payment endpoints send.
     *
     * @return void
     * @throws \Exception
     */
    protected function initializePostParams()
    {
        if ($this->requestId !== null && $this->requestId !== '') {
            $this->setParam('request_id', $this->requestId);
        }
        $this->ensureRequestId();
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
