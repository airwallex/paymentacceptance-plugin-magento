<?php

namespace Airwallex\Payments\Helper;

use Airwallex\Payments\Model\Config\Source\Mode;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Model\MethodInterface;

class Configuration extends AbstractHelper
{
    public const MODULE_NAME = 'Airwallex_Payments';
    private const DEMO_BASE_URL = 'https://pci-api-demo.airwallex.com/api/v1/';
    private const PRODUCTION_BASE_URL = 'https://pci-api.airwallex.com/api/v1/';

    private const EXPRESS_PREFIX = 'payment/airwallex_payments_express/';

    /**
     * Client id
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->scopeConfig->getValue('airwallex/general/' . $this->getMode() . '_client_id');
    }

    /**
     * Api key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return $this->scopeConfig->getValue('airwallex/general/' . $this->getMode() . '_api_key');
    }

    /**
     * Is request logger enabled
     *
     * @return bool
     */
    public function isRequestLoggerEnable(): bool
    {
        return (bool)$this->scopeConfig->getValue('airwallex/general/request_logger');
    }

    /**
     * Webhook secret key
     *
     * @return string
     */
    public function getWebhookSecretKey(): string
    {
        return $this->scopeConfig->getValue(
            'airwallex/general/webhook_' . $this->getMode() . '_secret_key'
        );
    }

    /**
     * Webhook
     *
     * @return array
     */
    public function getWebhook(): array
    {
        $str = $this->scopeConfig->getValue(
            'airwallex/general/webhook'
        );
        if (!$str) return [];
        $arr = json_decode($str, true);
        if (json_last_error() !== JSON_ERROR_NONE) return [];
        return $arr ?: [];
    }

    /**
     * Mode
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->scopeConfig->getValue('airwallex/general/mode');
    }

    /**
     * Api url
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->isDemoMode() ? self::DEMO_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * Is demo mode
     *
     * @return bool
     */
    public function isDemoMode(): bool
    {
        return $this->getMode() === Mode::DEMO;
    }

    /**
     * Card capture enabled
     *
     * @return bool
     */
    public function isCardCaptureEnabled(): bool
    {
        return $this->scopeConfig->getValue('payment/airwallex_payments_card/airwallex_payment_action')
            === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Pre Verification enabled
     *
     * @return bool
     */
    public function isPreVerificationEnabled(): bool
    {
        return $this->scopeConfig->getValue('payment/airwallex_payments_card/preverification');
    }

    /**
     * Card enabled
     *
     * @return bool
     */
    public function isCardActive(): bool
    {
        return !!$this->scopeConfig->getValue('payment/airwallex_payments_card/active');
    }

    /**
     * Card Vault enabled
     *
     * @return bool
     */
    public function isCardVaultActive(): bool
    {
        if (!$this->isCardActive()) return false;
        return !!$this->scopeConfig->getValue('payment/airwallex_payments_card/vault_active');
    }

    /**
     * getCardFontSize
     *
     * @return int
     */
    public function getCardFontSize(): int
    {
        $size = (int)$this->scopeConfig->getValue('payment/airwallex_payments_card/fontsize');
        if ($size < 12) $size = 12;
        if ($size > 20) $size = 20;
        return $size;
    }

    /**
     * getCardMaxWidth
     *
     * @return int
     */
    public function getCardMaxWidth(): int
    {
        $size = (int)$this->scopeConfig->getValue('payment/airwallex_payments_card/max_width');
        if ($size < 320) $size = 320;
        if ($size > 500) $size = 500;
        return $size;
    }

    /**
     * Express capture enabled
     *
     * @return bool
     */
    public function isExpressCaptureEnabled(): bool
    {
        return $this->scopeConfig->getValue('payment/airwallex_payments_express/airwallex_payment_action')
            === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Express display area
     *
     * @return string
     */
    public function expressDisplayArea(): string
    {
        return $this->scopeConfig->getValue('payment/airwallex_payments_express/display_area');
    }

    /**
     * Express seller name
     *
     * @return string
     */
    public function getExpressSellerName(): string
    {
        return $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'seller_name') ?: '';
    }

    /**
     * Express checkout
     *
     * @return string
     */
    public function getCheckout(): string
    {
        return $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'checkout') ?: '';
    }

    /**
     * Express style
     *
     * @return array
     */
    public function getExpressStyle(): array
    {
        return [
            "button_height" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'button_height'),
            "theme" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'theme'),
            "call_to_action" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'calltoaction'),
            // "apple_pay_button_theme" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'apple_pay_button_theme'),
            // "google_pay_button_theme" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'google_pay_button_theme'),
            // "apple_pay_button_type" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'apple_pay_button_type'),
            // "google_pay_button_type" => $this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'google_pay_button_type'),
        ];
    }

    /**
     * Is express active
     *
     * @return bool
     */
    public function isExpressActive(): bool
    {
        if (!$this->isCardActive()) {
            return false;
        }
        return !!$this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'active');
    }

    /**
     * Is express phone required
     *
     * @return bool
     */
    public function isExpressPhoneRequired(): bool
    {
        return $this->scopeConfig->getValue('customer/address/telephone_show') === "req";
    }

    /**
     * recaptcha type
     *
     * @return string
     */
    public function recaptchaType(): string
    {
        return $this->scopeConfig->getValue('recaptcha_frontend/type_for/place_order') ?? '';
    }

    /**
     * Country code
     *
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->scopeConfig->getValue('paypal/general/merchant_country')
            ?: $this->scopeConfig->getValue('general/country/default');
    }

    /**
     * Express button sort
     *
     * @return array
     */
    public function getExpressButtonSort(): array
    {
        $sorts = [];
        $sorts['google'] = (int)$this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'google_pay_sort_order');
        $sorts['apple'] = (int)$this->scopeConfig->getValue(self::EXPRESS_PREFIX . 'apple_pay_sort_order');
        asort($sorts);
        return array_keys($sorts);
    }

    /**
     * @return bool
     */
    public function isCvcRequired(): bool
    {
        return !!$this->scopeConfig->getValue('payment/airwallex_payments_card/cvc_required');
    }
}
