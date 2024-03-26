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
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class Retrieve extends AbstractClient implements BearerAuthenticationInterface
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
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents/' . $this->paymentConsentId;
    }

    /**
     * @param ResponseInterface $request
     *
     * @return stdClass
     * @throws JsonException
     * @throws LocalizedException
     */
    protected function parseResponse(ResponseInterface $request)
    {
        if ($this->paymentConsentId === null) {
            throw new LocalizedException(__('Payment Consent ID not set'));
        }

        return $this->parseJson($request);
    }
}
