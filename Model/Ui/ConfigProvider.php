<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit,
 * Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
 * please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Airwallex\Payments\Model\Ui;

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\PaymentConsents;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ReCaptchaUi\Block\ReCaptcha;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const AIRWALLEX_RECAPTCHA_FOR = 'place_order';

    protected Configuration $configuration;
    protected IsCaptchaEnabledInterface $isCaptchaEnabled;
    protected ReCaptcha $reCaptchaBlock;
    protected Session $customerSession;
    protected PaymentConsentsInterface $paymentConsents;

    /**
     * ConfigProvider constructor.
     * @param Configuration $configuration
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param ReCaptcha $reCaptchaBlock
     * @param Session $customerSession
     * @param PaymentConsentsInterface $paymentConsents
     */
    public function __construct(
        Configuration             $configuration,
        IsCaptchaEnabledInterface $isCaptchaEnabled,
        ReCaptcha                 $reCaptchaBlock,
        Session                   $customerSession,
        PaymentConsentsInterface  $paymentConsents
    ) {
        $this->configuration = $configuration;
        $this->isCaptchaEnabled = $isCaptchaEnabled;
        $this->reCaptchaBlock = $reCaptchaBlock;
        $this->customerSession = $customerSession;
        $this->paymentConsents = $paymentConsents;
    }

    /**
     * Adds mode to checkout config array
     *
     * @return array
     * @throws InputException
     */
    public function getConfig(): array
    {
        $recaptchaEnabled = $this->isReCaptchaEnabled();
        $config = [
            'payment' => [
                'airwallex_payments' => [
                    'mode' => $this->configuration->getMode(),
                    'cc_auto_capture' => $this->configuration->isCardCaptureEnabled(),
                    'recaptcha_enabled' => !!$recaptchaEnabled,
                    'cvc_required' => $this->configuration->isCvcRequired()
                ]
            ]
        ];

        if ($recaptchaEnabled) {
            $config['payment']['airwallex_payments']['recaptcha_settings'] = $this->getReCaptchaConfig();
        }

        if ($this->customerSession->isLoggedIn() && $customer = $this->customerSession->getCustomer()) {
            $airwallexCustomerId = $customer->getData(PaymentConsents::KEY_AIRWALLEX_CUSTOMER_ID);
            if (!$airwallexCustomerId) {
                $airwallexCustomerId = $this->paymentConsents->createAirwallexCustomer($customer->getId());
            }
            $config['payment']['airwallex_payments']['customer_id'] = $airwallexCustomerId;
        }

        return $config;
    }

    /**
     * Get reCaptcha config
     *
     * @return array
     */
    public function getReCaptchaConfig()
    {
        if (!$this->isReCaptchaEnabled()) {
            return [];
        }

        $this->reCaptchaBlock->setData([
            'recaptcha_for' => self::AIRWALLEX_RECAPTCHA_FOR
        ]);

        return $this->reCaptchaBlock->getCaptchaUiConfig();
    }

    /**
     * Get is reCaptcha enabled
     *
     * @return bool
     */
    public function isReCaptchaEnabled()
    {
        return $this->isCaptchaEnabled->isCaptchaEnabledFor(self::AIRWALLEX_RECAPTCHA_FOR);
    }
}
