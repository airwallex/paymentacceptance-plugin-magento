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
    public function setPaymentConsentId(string $paymentConsentId): Retrieve
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
     * @param ResponseInterface $response
     *
     * @return mixed|object
     * @throws JsonException
     * @throws LocalizedException
     */
    protected function parseResponse(ResponseInterface $response): stdClass
    {
        if ($this->paymentConsentId === null) {
            throw new LocalizedException(__('Payment Consent ID not set'));
        }

        return $this->parseJson($response);
    }
}
