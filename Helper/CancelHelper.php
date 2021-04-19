<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
