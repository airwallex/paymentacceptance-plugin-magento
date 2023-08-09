<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Model\Adminhtml\Notifications;

use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Magento\Framework\Notification\MessageInterface;

class Dependencies implements MessageInterface
{
    /**
     * @var string|null
     */
    private ?string $displayedText = null;

    /**
     * @var AvailablePaymentMethodsHelper
     */
    private AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;

    /**
     * Dependencies constructor.
     *
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     */
    public function __construct(AvailablePaymentMethodsHelper $availablePaymentMethodsHelper)
    {
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->hasDependencies();
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return 'airwallex_payments_notification_dependencies';
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
    private function hasDependencies(): void
    {
        if (!$this->availablePaymentMethodsHelper->isMobileDetectInstalled()) {
            $this->displayedText = __('The Airwallex Payment module is missing dependency
                Please run "composer require mobiledetect/mobiledetectlib^2.8" to install the dependency.');
        }
    }
}
