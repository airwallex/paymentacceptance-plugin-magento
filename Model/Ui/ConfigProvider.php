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

use Airwallex\Payments\Helper\Configuration;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Block\ReCaptcha;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const AIRWALLEX_RECAPTCHA_FOR = 'airwallex_card';

    protected Configuration $configuration;
    protected IsCaptchaEnabledInterface $isCaptchaEnabled;
    protected ReCaptcha $reCaptchaBlock;

    /**
     * ConfigProvider constructor.
     * @param Configuration $configuration
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param ReCaptcha $reCaptchaBlock
     */
    public function __construct(
        Configuration             $configuration,
        IsCaptchaEnabledInterface $isCaptchaEnabled,
        ReCaptcha                 $reCaptchaBlock
    )
    {
        $this->configuration = $configuration;
        $this->isCaptchaEnabled = $isCaptchaEnabled;
        $this->reCaptchaBlock = $reCaptchaBlock;
    }

    /**
     * Adds mode to checkout config array
     *
     * @return array
     * @throws InputException
     */
    public function getConfig(): array
    {
        $recaptchaEnabled = $this->isCaptchaEnabled->isCaptchaEnabledFor(self::AIRWALLEX_RECAPTCHA_FOR);
        $config = [
            'payment' => [
                'airwallex_payments' => [
                    'mode' => $this->configuration->getMode(),
                    'express_seller_name' => $this->configuration->getExpressSellerName(),
                    'is_express_active' => $this->configuration->isExpressActive(),
                    'is_express_phone_required' => $this->configuration->isExpressPhoneRequired(),
                    'is_express_capture_enabled' => $this->configuration->isExpressCaptureEnabled(),
                    'express_style' => $this->configuration->getExpressStyle(),
                    'express_button_sort' => $this->configuration->getExpressButtonSort(),
                    'cc_auto_capture' => $this->configuration->isCaptureEnabled(),
                    'recaptcha_enabled' => !!$recaptchaEnabled,
                    'country_code' => $this->configuration->getCountryCode(),
                ]
            ]
        ];

        if ($recaptchaEnabled) {
            $this->reCaptchaBlock->setData([
                'recaptcha_for' => self::AIRWALLEX_RECAPTCHA_FOR
            ]);
            $config['payment']['airwallex_payments']['recaptcha_settings']
                = $this->reCaptchaBlock->getCaptchaUiConfig();
        }

        return $config;
    }
}
