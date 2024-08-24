<?php

namespace Airwallex\Payments\Helper;

class CancelHelper
{
    /**
     * @var bool
     */
    private bool $webhookCanceling = false;

    /**
     * @return bool
     */
    public function isWebhookCanceling(): bool
    {
        return $this->webhookCanceling;
    }

    /**
     * @param bool $webhookCanceling
     */
    public function setWebhookCanceling(bool $webhookCanceling): void
    {
        $this->webhookCanceling = $webhookCanceling;
    }
}
