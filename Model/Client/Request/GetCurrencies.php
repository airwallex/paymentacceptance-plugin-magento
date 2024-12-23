<?php

namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class GetCurrencies extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/config/currency_settings';
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     * @return AbstractClient
     */
    public function setPage(int $pageNumber, int $pageSize = 20): AbstractClient
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $response = $this->parseJson($response);

        $result = [];
        if (empty($response->items)) return [
            'has_more' => false,
            'items' => $result,
        ];
        foreach ($response->items as $item) {
            if ($item->type !== 'currency_switcher') continue;
            if (empty($item->currencies)) continue;
            $result = array_merge($result, $item->currencies);
        }

        return [
            'has_more' => $response->has_more,
            'items' => $result,
        ];
    }
}
