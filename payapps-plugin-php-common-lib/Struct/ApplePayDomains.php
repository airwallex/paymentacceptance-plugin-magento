<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class ApplePayDomains extends AbstractBase
{
    /**
     * @var array
     */
    private $items;

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
     * @return ApplePayDomains
     */
    public function setItems(array $items): ApplePayDomains
    {
        $this->items = $items;
        return $this;
    }

    /**
     * @param string $domain
     *
     * @return bool
     */
    public function hasDomain(string $domain): bool
    {
        return in_array($domain, $this->getItems(), true);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->getItems());
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->getItems());
    }
}