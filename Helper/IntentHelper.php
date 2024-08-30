<?php

namespace Airwallex\Payments\Helper;

class IntentHelper
{
    private array $intent;

    /**
     * @return array
     */
    public function getIntent(): array
    {
        return $this->intent;
    }

    /**
     * @param array $intent
     */
    public function setIntent(array $intent): void
    {
        $this->intent = $intent;
    }
}
