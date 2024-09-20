<?php

namespace Airwallex\Payments\Model\Adminhtml\Notifications;

use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\Notification\MessageInterface;

class ExpressDisabled implements MessageInterface
{
    /**
     * @var string|null
     */
    private ?string $displayedText = null;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * Dependencies constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
        $this->expressDisabled();
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return 'airwallex_payments_notification_express_disabled';
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
        return self::SEVERITY_NOTICE;
    }

    /**
     * @return void
     */
    private function expressDisabled(): void
    {
        if (!$this->configuration->isCardActive()) {
            $this->displayedText = __('Airwallex Express Checkout is also disabled.'
                . ' To use Express Checkout, you must first enable Credit Card payments.');
        }
    }
}
