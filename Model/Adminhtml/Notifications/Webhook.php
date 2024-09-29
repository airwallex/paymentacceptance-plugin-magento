<?php

namespace Airwallex\Payments\Model\Adminhtml\Notifications;

use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Webhook\Webhook as WebhookWebhook;
use Exception;
use Magento\Framework\Notification\MessageInterface;

class Webhook implements MessageInterface
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
     * Upgrade constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->checkWebhook();
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return 'airwallex_payments_notification_webhook';
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
    private function checkWebhook(): void
    {
        try {
            $mode = $this->configuration->getMode();
            $capitalMode = ucfirst($mode);
            if ($capitalMode === 'Prod') {
                $capitalMode = 'Production';
            }
            if (!$this->configuration->getWebhookSecretKey()) {
                $this->displayedText = __("Please ensure that the Airwallex Webhook $capitalMode Secret Key is configured.");
                return;
            }
            $webhook = $this->configuration->getWebhook();
            $mode = $this->configuration->getMode();
            if (empty($webhook["{$mode}_key"])) return;
            if ($webhook["{$mode}_key"] !== $this->configuration->getWebhookSecretKey()) return;
            if ($webhook["{$mode}_status"] !== WebhookWebhook::WEBHOOK_VERIFIED) {
                $this->displayedText = __("The Airwallex Webhook $capitalMode Secret Key is not configured properly. Please ensure it is correctly set up.");
            }
        } catch (Exception $e) {
            return;
        }
    }
}
