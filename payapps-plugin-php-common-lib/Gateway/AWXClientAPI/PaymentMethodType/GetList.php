<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethodType;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\GetList as GetListStruct;

class GetList extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/config/payment_method_types';
    }

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param bool $active
     *
     * @return GetList
     */
    public function setActive(bool $active): GetList
    {
        return $this->setParam('active', $active);
    }

    /**
     * @param bool $includeResources
     *
     * @return GetList
     */
    public function setIncludeResources(bool $includeResources): GetList
    {
        return $this->setParam('__resources', $includeResources);
    }

    /**
     *
     * @param string $countryCode
     *
     * @return GetList
     */
    public function setCountryCode(string $countryCode): GetList
    {
        return $this->setParam('country_code', $countryCode);
    }

    /**
     * @param string $transactionMode
     *
     * @return GetList
     */
    public function setTransactionMode(string $transactionMode): GetList
    {
        return $this->setParam('transaction_mode', $transactionMode);
    }

    /**
     * @param string $transactionCurrency
     *
     * @return GetList
     */
    public function setTransactionCurrency(string $transactionCurrency): GetList
    {
        return $this->setParam('transaction_currency', $transactionCurrency);
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     *
     * @return GetList
     */
    public function setPage(int $pageNumber = 0, int $pageSize = 100): GetList
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param $response
     *
     * @return GetListStruct
     */
    protected function parseResponse($response): GetListStruct
    {
        $items = [];
        $responseArray = json_decode((string)$response->getBody(), true);
        if (!empty($responseArray['items'])) {
            foreach ($responseArray['items'] as $item) {
                $items[] = new PaymentMethodType($item);
            }
        }
        return new GetListStruct([
            'items' => $items,
            'has_more' => $responseArray['has_more'],
        ]);
    }
}
