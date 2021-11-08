<?php

namespace Airwallex\Payments\Model\Ui;

use \Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Payment\Model\MethodInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const MODE_PATH = 'payment/airwallex_payments_basic/mode';

    const CAPTURE_PATH = 'payment/airwallex_payments_card/airwallex_payment_action';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * ConfigProvider constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Adds mode to checkout config array
     *
     * @return \array[][]
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'airwallex_payments' => [
                    'mode' => $this->scopeConfig->getValue(self::MODE_PATH) ?? 'demo',
                    'cc_auto_capture' => $this->isCaptureEnabled()
                ]
            ]
        ];
        return $config;
    }

    /**
     * @return bool
     */
    private function isCaptureEnabled() {
        return $this->scopeConfig->getValue(self::CAPTURE_PATH) === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
    }

}
