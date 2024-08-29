<?php

namespace Airwallex\Payments\Helper;

class IsOrderCreatedHelper
{
    /**
     * @var bool
     */
    private bool $isOrderCreated = true;

    /**
     * @return bool
     */
    public function isCreated(): bool
    {
        return $this->isOrderCreated;
    }

    /**
     * @param bool $isCreated
     */
    public function setIsCreated(bool $isCreated): void
    {
        $this->isOrderCreated = $isCreated;
    }
}
