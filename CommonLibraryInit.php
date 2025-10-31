<?php

namespace Airwallex\Payments;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\CacheService;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheManager;

class CommonLibraryInit
{
    protected Configuration $configuration;
    protected ModuleListInterface $moduleList;
    protected ProductMetadataInterface $productMetadata;

    public function __construct(
        Configuration $configuration,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata
    )
    {
        $this->configuration = $configuration;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
    }

    public function exec()
    {
        Init::getInstance([
            'env' => $this->configuration->getMode(),
            'client_id' => $this->configuration->getClientId(),
            'api_key' => $this->configuration->getApiKey(),
            'plugin_type' => 'magento',
            'plugin_version' => $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version'],
            'platform_version' => $this->productMetadata->getVersion(),
        ]);

        CacheManager::setInstance(new CacheService($this->configuration->getClientId()));
    }
}
