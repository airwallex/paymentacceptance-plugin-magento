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
     * ExpressDisabled constructor.
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
