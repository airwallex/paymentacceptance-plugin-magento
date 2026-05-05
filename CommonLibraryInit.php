<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
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

        CacheManager::setInstance(new CacheService());
    }
}
