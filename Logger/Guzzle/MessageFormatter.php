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
namespace Airwallex\Payments\Logger\Guzzle;

use Exception;
use GuzzleHttp\MessageFormatter as BaseMessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * @param \Throwable|null $error Exception that was received
     *
     * @return string
     */
    public function format(
        RequestInterface $request,
        ResponseInterface $response = null,
        \Throwable $error = null
    ): string {
        foreach (self::WITHOUT_HEADERS as $header) {
            $request = $request->withoutHeader($header);
        }

        return parent::format($request, $response, $error);
    }
}
