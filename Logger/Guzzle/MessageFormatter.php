<?php

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
        ResponseInterface $response = null,
        Throwable         $error = null
    ): string
    {
        foreach (self::WITHOUT_HEADERS as $header) {
            $request = $request->withoutHeader($header);
        }

        return parent::format($request, $response, $error);
    }
}
