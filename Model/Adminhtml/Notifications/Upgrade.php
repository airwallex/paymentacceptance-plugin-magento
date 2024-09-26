<?php

namespace Airwallex\Payments\Model\Adminhtml\Notifications;

use Airwallex\Payments\Helper\Configuration;
use Exception;
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

    /**
     * Upgrade constructor.
     *
     * @param Configuration $configuration
     * @param ModuleListInterface $moduleList
     */
    public function __construct(Configuration $configuration, ModuleListInterface $moduleList)
    {
        $this->configuration = $configuration;
        $this->moduleList = $moduleList;
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
        $packageName = 'airwallex/payments-plugin-magento';
        try {
            $content = file_get_contents('https://packagist.org/p2/' . $packageName . '.json');
        } catch (Exception $e) {
            return;
        }
        if (empty($content)) return;
        $data = json_decode($content, true);
        if (isset($data['packages'][$packageName])) {
            $version = $data['packages'][$packageName][0]['version'] ?? '';
            if (empty($version)) return;
            $currentVersion = $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version'];
            if (version_compare($version, $currentVersion, '>')) {
                $this->displayedText = __("For the best performance and access to new features, please update your Airwallex Payment plugin "
                    . "to the latest version, $version. Your current version is $currentVersion.");
            }
        }
    }
}
