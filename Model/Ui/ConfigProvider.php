<?php

namespace Airwallex\Payments\Model\Ui;

use Airwallex\Payments\Helper\Configuration;
use \Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * ConfigProvider constructor.
     * @param Configuration $configuration
     */
    public function __construct(
        Configuration $configuration
    ) {
        $this->configuration = $configuration;
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
                    'mode' => $this->configuration->getMode(),
                    'cc_auto_capture' => $this->configuration->isCaptureEnabled()
                ]
            ]
        ];
        return $config;
    }
}
