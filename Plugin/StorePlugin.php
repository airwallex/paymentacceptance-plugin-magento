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
namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Model\PaymentIntents;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class StorePlugin
{
    /**
     * @var PaymentIntents
     */
    private PaymentIntents $paymentIntents;

    /**
     * ClearSavedIntent constructor.
     *
     * @param PaymentIntents $paymentIntents
     */
    public function __construct(PaymentIntents $paymentIntents)
    {
        $this->paymentIntents = $paymentIntents;
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterSetCurrentCurrencyCode(): void
    {
        $this->paymentIntents->removeIntents();
    }
}
