<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\GetList as StructGetList;

class GetList extends AbstractApi
{
    private $strict = false;

    public function setStrict(bool $strict): GetList
    {
        $this->strict = $strict;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * A bookmark used in pagination to retrieve the next or previous page of
     * results. Use the page_after / page_before value from a previous call.
     *
     * @param string $page
     *
     * @return GetList
     */
    public function setPage(string $page): GetList
    {
        return $this->setParam('page', $page);
    }

    /**
     * Number of Webhooks per page. Defaults to 20 on the API side.
     *
     * @param int $pageSize
     *
     * @return GetList
     */
    public function setPageSize(int $pageSize): GetList
    {
        return $this->setParam('page_size', $pageSize);
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'webhooks';
    }

    /**
     * @param $response
     *
     * @return StructGetList
     */
    protected function parseResponse($response): StructGetList
    {
        if ($this->strict) {
            $data = json_decode((string)$response->getBody());
            if (!is_object($data) || !isset($data->items) || !is_array($data->items)
                || (isset($data->page_after) && !is_string($data->page_after))) {
                throw new \Exception('Invalid webhook list response.');
            }
            foreach ($data->items as $item) {
                if (!is_object($item) || !is_string($item->id ?? null) || !is_string($item->url ?? null)
                    || !is_string($item->version ?? null) || !is_array($item->events ?? null)
                    || count($item->events) !== count(array_filter($item->events, 'is_string'))) {
                    throw new \Exception('Invalid webhook list response.');
                }
            }
        }
        $items = [];
        // Guard against a null/non-array json_decode result (empty or invalid body).
        $responseArray = json_decode((string)$response->getBody(), true) ?: [];
        if (!empty($responseArray['items'])) {
            foreach ($responseArray['items'] as $item) {
                $items[] = new Webhook($item);
            }
        }

        return new StructGetList([
            'items' => $items,
            'page_after' => $responseArray['page_after'] ?? '',
            'page_before' => $responseArray['page_before'] ?? '',
        ]);
    }
}
