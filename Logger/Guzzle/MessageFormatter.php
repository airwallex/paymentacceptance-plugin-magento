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
namespace Airwallex\Payments\Logger\Guzzle;

use GuzzleHttp\MessageFormatter as BaseMessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class MessageFormatter extends BaseMessageFormatter
{
    private const WITHOUT_HEADERS = [
        'Authorization',
        'x-client-id',
        'x-api-key'
    ];

    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface $request Request that was sent
     * @param ResponseInterface|null $response Response that was received
     * @param Throwable|null $error Exception that was received
     *
     * @return string
     */
    public function format(
        RequestInterface  $request,
        ?ResponseInterface $response = null,
        ?Throwable         $error = null
    ): string
    {
        foreach (self::WITHOUT_HEADERS as $header) {
            $request = $request->withoutHeader($header);
        }

        return parent::format($request, $response, $error);
    }
}
