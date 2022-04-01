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
namespace Airwallex\Payments\Model\Client\Request;

use Airwallex\Payments\Model\Client\AbstractClient;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Authentication extends AbstractClient
{
    private const X_API_VERSION = '2020-04-30';

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
     * @param ResponseInterface $request
     *
     * @return object
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $request): object
    {
        return $this->parseJson($request);
    }
}
