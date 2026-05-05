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
namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class AvailablePaymentMethods extends AbstractClient implements BearerAuthenticationInterface
{
    public const TRANSACTION_MODE = 'oneoff';

    public string $cacheName = 'available_payment_method_types';

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): self
    {
        return $this->setParam('transaction_currency', $currency);
    }
    public function removeCurrency(): self
    {
        return $this->unsetParam('transaction_currency');
    }

    public function setResources(): self
    {
        return $this->setParam('__resources', 'true');
    }

    public function setActive(): self
    {
        return $this->setParam('active', 'true');
    }

    public function setTransactionMode(string $mode): self
    {
        return $this->setParam('transaction_mode', $mode);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/config/payment_method_types';
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();
        if (empty($content)) return [];
        $content = json_decode($content, true);
        return $content['items'] ?? [];
    }
}
