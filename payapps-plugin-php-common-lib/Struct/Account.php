<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Account extends AbstractBase
{
    /**
     * @var string
     */
    private $owningEntity;

    /**
     * @return string
     */
    public function getOwningEntity(): string
    {
        return $this->owningEntity ?? '';
    }

    /**
     * @param string $owningEntity
     *
     * @return self
     */
    public function setOwningEntity(string $owningEntity): self
    {
        $this->owningEntity = $owningEntity;
        return $this;
    }
}
