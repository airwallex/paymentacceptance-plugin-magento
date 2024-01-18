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

namespace Airwallex\Payments\Block\Checkout;

use Airwallex\Payments\Helper\Verification as VerificationHelper;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;

class PowVerification extends AbstractBlock
{
    public function __construct(
        Context $context,
        protected VerificationHelper $verificationHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @throws \Random\RandomException
     */
    public function toHtml(): string
    {
        if (!$this->verificationHelper->isPOWEnabled()) {
            return '';
        }

        $powData = json_encode([
            'nonce' => $this->_escaper->escapeJs($this->getNonce()),
            'difficulty' => VerificationHelper::POW_COMPLEXITY,
            'prefix' => VerificationHelper::POW_PREFIX,
            'separator' => VerificationHelper::POW_SEPARATOR,
        ]);
        return "<script>window.airwallex_pow = $powData;</script>";
    }

    /**
     * @throws \Random\RandomException
     */
    protected function getNonce(): string
    {
        return $this->verificationHelper->getNonce();
    }
}
