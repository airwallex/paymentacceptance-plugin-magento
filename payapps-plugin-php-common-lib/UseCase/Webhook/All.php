<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook\GetList as GetWebhookList;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Exception;

class All
{
    /**
     * @var int
     */
    private $pageSize = 100;

    /**
     * @param int $pageSize
     *
     * @return All
     */
    public function setPageSize(int $pageSize): All
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Fetch every webhook by walking the cursor-based pagination until the API
     * stops returning a next-page cursor.
     *
     * @return Webhook[]
     * @throws Exception
     */
    public function get(): array
    {
        $page = '';
        $all = [];
        $maxPage = 100;
        $pageCount = 0;
        do {
            $request = $this->createGetList()->setPageSize($this->pageSize);
            if ($page !== '') {
                $request->setPage($page);
            }
            $getList = $request->send();

            /** @var Webhook $webhook */
            foreach ($getList->getItems() as $webhook) {
                $all[] = $webhook;
            }

            $page = $getList->getPageAfter();
            $pageCount++;
        } while ($pageCount < $maxPage && $page !== '');

        return $all;
    }

    /**
     * @return GetWebhookList
     */
    protected function createGetList(): GetWebhookList
    {
        return new GetWebhookList();
    }
}
