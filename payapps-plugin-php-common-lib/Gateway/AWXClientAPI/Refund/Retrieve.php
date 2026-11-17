<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Refund as StructRefund;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Retrieve extends AbstractApi
{
    /**
     * @var string
     */
    private $id = '';

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/refunds/' . UrlHelper::pathSegment($this->id);
    }

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param string $id
     *
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param $response
     *
     * @return StructRefund
     */
    protected function parseResponse($response): StructRefund
    {
        return new StructRefund(json_decode((string) $response->getBody(), true) ?: []);
    }
}
