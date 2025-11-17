<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\Config;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\GetAvailableCurrencies;
use Exception;

class CurrencySwitcherAvailableCurrencies
{
    use CacheTrait;

    /**
     * @return array
     * @throws Exception
     */
    public function get(): array
    {
        $cacheName = "awx_available_currencies";
        return $this->cacheRemember(
            $cacheName,
            function () {
                return $this->getAvailableCurrencies();
            }
        );
    }

    public function getAvailableCurrencies(): array
    {
        $page = 0;
        $all = [];
        $maxPage = 100;
        $getCurrenciesRequest = new GetAvailableCurrencies();
        do {
            $getList = $getCurrenciesRequest->setPage($page)->send();
            foreach ($getList->getItems() as $items) {
                $all[] = $items;
            }
            $page++;
        } while ($page < $maxPage && $getList->hasMore());
        foreach ($all as $item) {
            if (isset($item['type']) && $item['type'] === 'currency_switcher') {
                return $item['currencies'] ?? [];
            }
        }
        return [];
    }
}
