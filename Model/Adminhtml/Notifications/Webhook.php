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
