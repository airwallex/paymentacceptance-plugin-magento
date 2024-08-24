<?php

namespace Airwallex\Payments\Model\Webhook;

abstract class AbstractWebhook
{
    /**
     * @param object $data
     *
     * @return void
     */
    abstract public function execute(object $data): void;
}
