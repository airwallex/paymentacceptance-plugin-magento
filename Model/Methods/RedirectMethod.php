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

use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Mobile_Detect;
use Exception;

class RedirectMethod extends AbstractMethod
{
    public const DANA_CODE = 'airwallex_payments_dana';
    public const ALIPAYCN_CODE = 'airwallex_payments_alipaycn';
    public const ALIPAYHK_CODE = 'airwallex_payments_alipayhk';
    public const GCASH_CODE = 'airwallex_payments_gcash';
    public const KAKAO_CODE = 'airwallex_payments_kakaopay';
    public const TOUCH_N_GO_CODE = 'airwallex_payments_tng';

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return $this
     * @throws GuzzleException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function authorize(InfoInterface $payment, $amount): self
    {
        parent::authorize($payment, $amount);

        $detect = new Mobile_Detect();

        try {
            $returnUrl = $this->confirm
                ->setPaymentIntentId($this->getIntentId())
                ->setInformation($this->getPaymentMethodCode(), $detect->isMobile(), $this->getMobileOS($detect))
                ->send();
        } catch (Exception $exception) {
            throw new LocalizedException(__($exception->getMessage()));
        }

        $this->checkoutHelper->getCheckout()->setAirwallexPaymentsRedirectUrl($returnUrl);

        return $this;
    }

    /**
     * @param Mobile_Detect $detect
     *
     * @return string|null
     */
    private function getMobileOS(Mobile_Detect $detect): ?string
    {
        if (!$detect->isMobile()) {
            return null;
        }

        return $detect->isAndroidOS() ? 'android' : 'ios';
    }

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
