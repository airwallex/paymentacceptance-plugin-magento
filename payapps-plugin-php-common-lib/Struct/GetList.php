<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class GetList extends AbstractBase
{
    /**
     * @var bool
     */
    private $hasMore;

    /**
     * @var string
     */
    private $pageBefore;

    /**
     * @var string
     */
    private $pageAfter;

    /**
     * @var array
     */
    private $items;

    /**
     * @return bool
     */
    public function hasMore(): bool
    {
        return $this->hasMore ?? false;
    }

    /**
     * @param bool $hasMore
     *
     * @return GetList
     */
    public function setHasMore(bool $hasMore): GetList
    {
        $this->hasMore = $hasMore;
        return $this;
    }

    /**
     * @return string
     */
    public function getPageBefore(): string
    {
        return $this->pageBefore ?? '';
    }

    /**
     * @param string $pageBefore
     *
     * @return GetList
     */
    public function setPageBefore(string $pageBefore): GetList
    {
        $this->pageBefore = $pageBefore;
        return $this;
    }


    /**
     * @return string
     */
    public function getPageAfter(): string
    {
        return $this->pageAfter ?? '';
    }

    /**
     * @param string $pageAfter
     *
     * @return GetList
     */
    public function setPageAfter(string $pageAfter): GetList
    {
        $this->pageAfter = $pageAfter;
        return $this;
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * @param array $items
     *
     * @return GetList
     */
    public function setItems(array $items): GetList
    {
        $this->items = $items;
        return $this;
    }
}
