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
namespace Airwallex\Payments\Model\Client\Request\PaymentConsent;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Http\Message\ResponseInterface;

class Disable extends AbstractClient implements BearerAuthenticationInterface
{
    protected ?string $paymentConsentId = null;

    /**
     * @param string $paymentConsentId
     * @return $this
     */
    public function setPaymentConsentId(string $paymentConsentId)
    {
        $this->paymentConsentId = $paymentConsentId;

        return $this;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents/' . $this->paymentConsentId . '/disable';
    }

    /**
     * @param ResponseInterface $request
     *
     * @return string
     * @throws \JsonException
     * @throws LocalizedException
     */
    protected function parseResponse(ResponseInterface $request): string
    {
        if ($this->paymentConsentId === null) {
            throw new LocalizedException(__('Payment Consent ID not set'));
        }

        $request = $this->parseJson($request);

        return $request->status === 'DISABLED';
    }
}
