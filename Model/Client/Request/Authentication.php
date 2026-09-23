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
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Authentication extends AbstractClient
{
    public const X_API_VERSION = '2024-06-30';

    /**
     * @return string[]
     */
    protected function getHeaders(): array
    {
        return [
            'x-client-id' => $this->configuration->getClientId(),
            'x-api-key' => $this->configuration->getApiKey(),
            'x-api-version' => self::X_API_VERSION
        ];
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'authentication/login';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return object
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): object
    {
        return $this->parseJson($response);
    }
}
