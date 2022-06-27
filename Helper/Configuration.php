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

use Airwallex\Payments\Model\Config\Source\Mode;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Model\MethodInterface;

class Configuration extends AbstractHelper
{
    public const MODULE_NAME = 'Airwallex_Payments';
    private const DEMO_BASE_URL = 'https://pci-api-demo.airwallex.com/api/v1/';
    private const PRODUCTION_BASE_URL = 'https://pci-api.airwallex.com/api/v1/';

    /**
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->scopeConfig->getValue('airwallex/general/' . $this->getMode() . '_client_id');
    }

    /**
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->scopeConfig->getValue('airwallex/general/' . $this->getMode() . '_api_key');
    }

    /**
     * @return bool
     */
    public function isRequestLoggerEnable(): bool
    {
        return (bool) $this->scopeConfig->getValue('airwallex/general/request_logger');
    }

    /**
     * @return string
     */
    public function getWebhookSecretKey(): string
    {
        return $this->scopeConfig->getValue(
            'airwallex/general/webhook_' . $this->getMode() . '_secret_key'
        );
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->scopeConfig->getValue('airwallex/general/mode');
    }

    /**
     * @return string
     */
    public function getCardPaymentAction(): string
    {
        return $this->scopeConfig->getValue('payment/airwallex_payments_card/airwallex_payment_action');
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->isDemoMode() ? self::DEMO_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * @return bool
     */
    private function isDemoMode(): bool
    {
        return $this->getMode() === Mode::DEMO;
    }

    /**
     * @return bool
     */
    public function isCaptureEnabled() {
        return $this->scopeConfig->getValue('payment/airwallex_payments_card/airwallex_payment_action') === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
    }
}
