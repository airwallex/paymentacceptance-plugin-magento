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
namespace Airwallex\Payments\Model\Methods;

use Magento\Quote\Api\Data\CartInterface;

class EmbeddedMethod extends AbstractMethod
{
    public const GOOGLE_PAY_CODE = 'airwallex_payments_googlepay';
    public const APPLE_PAY_CODE = 'airwallex_payments_applepay';

    /**
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) &&
            $this->availablePaymentMethodsHelper->isMobileDetectInstalled();
    }
}
