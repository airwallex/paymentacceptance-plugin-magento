<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\GetList as StructGetList;

class GetAvailableCurrencies extends AbstractApi
{
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
        return 'pa/config/currency_settings';
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     *
     * @return GetAvailableCurrencies
     */
    public function setPage(int $pageNumber = 0, int $pageSize = 100): GetAvailableCurrencies
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param $response
     *
     * @return StructGetList
     */
    protected function parseResponse($response): StructGetList
    {
        $responseData = json_decode((string)$response->getBody(), true);
        return new StructGetList($responseData);
    }
}
