<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Terminal;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Terminal as StructTerminal;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\GetList as StructGetList;

class GetList extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @param string $status
     *
     * @return GetList
     */
    public function setStatus(string $status): GetList
    {
        return $this->setParam('status', $status);
    }

    /**
     * @param string $page
     *
     * @return GetList
     */
    public function setPage(string $page): GetList
    {
        return $this->setParam('page', $page);
    }

    /**
     * @param int $pageSize
     *
     * @return GetList
     */
    public function setPageSize(int $pageSize): GetList
    {
        return $this->setParam('page_size', $pageSize);
    }

    /**
     * @param string $deviceModel
     *
     * @return GetList
     */
    public function setDeviceModel(string $deviceModel): GetList
    {
        return $this->setParam('device_model', $deviceModel);
    }

    /**
     * @param string $nickname
     *
     * @return GetList
     */
    public function setNickname(string $nickname): GetList
    {
        return $this->setParam('nickname', $nickname);
    }

    /**
     * @param string $serialNumber
     *
     * @return GetList
     */
    public function setSerialNumber(string $serialNumber): GetList
    {
        return $this->setParam('serial_number', $serialNumber);
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/pos/terminals';
    }

    /**
     * @param $response
     *
     * @return StructGetList
     */
    protected function parseResponse($response): StructGetList
    {
        $items = [];
        $responseArray = json_decode((string)$response->getBody(), true);
        if (!empty($responseArray['items'])) {
            foreach ($responseArray['items'] as $item) {
                $items[] = new StructTerminal($item);
            }
        }
        return new StructGetList([
            'items' => $items,
            'page_after' => $responseArray['page_after'] ?? '',
            'page_before' => $responseArray['page_before'] ?? '',
        ]);
    }
}
