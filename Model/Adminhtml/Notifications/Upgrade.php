<?php

namespace Airwallex\Payments\Model\Adminhtml\Notifications;

use Airwallex\Payments\Helper\Configuration;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Notification\MessageInterface;

class Upgrade implements MessageInterface
{
    /**
     * @var string|null
     */
    private ?string $displayedText = null;

    /**
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * @var ModuleListInterface
     */
    protected ModuleListInterface $moduleList;

    protected CacheInterface $cache;

    /**
     * Upgrade constructor.
     *
     * @param Configuration $configuration
     * @param ModuleListInterface $moduleList
     * @param CacheInterface $cache
     */
    public function __construct(Configuration $configuration, ModuleListInterface $moduleList, CacheInterface $cache)
    {
        $this->configuration = $configuration;
        $this->moduleList = $moduleList;
        $this->cache = $cache;
        $this->shouldUpgrade();
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return 'airwallex_payments_notification_upgrade';
    }

    /**
     * @return bool
     */
    public function isDisplayed(): bool
    {
        return $this->displayedText !== null;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->displayedText;
    }

    /**
     * @return int
     */
    public function getSeverity(): int
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * @return void
     */
    private function shouldUpgrade(): void
    {
        try {
            $cacheName = 'airwallex_payments_plugin_magento_version';
            $version = $this->cache->load($cacheName);
            if ($version === false) {
                $version = $this->getCloudVersion();
                $this->cache->save($version, $cacheName, [], 3600 * 2);
            }

            if (!$version) {
                return;
            }

            $currentVersion = $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version'];
            if (version_compare($version, $currentVersion, '>')) {
                $this->displayedText = __("For the best performance and access to new features, please update your Airwallex Payment plugin "
                    . "to the latest version, $version. Your current version is $currentVersion.");
            }
        } catch (Exception $e) {
            return;
        }
    }

    private function getCloudVersion(): string
    {
        $ch = curl_init('https://commercemarketplace.adobe.com/airwallex-payments-plugin-magento.html');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $content = curl_exec($ch);
        if (!$content) return '';
        preg_match('/<p>(\d\.\d{1,2}\.\d{1,2})<\/p>/', $content, $matches);
        if (empty($matches)) return '';
        return $matches[1];
    }
}
