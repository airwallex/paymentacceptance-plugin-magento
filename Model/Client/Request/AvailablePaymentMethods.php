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
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class AvailablePaymentMethods extends AbstractClient implements BearerAuthenticationInterface
{
    private const TRANSACTION_MODE = 'oneoff';

    /**
     * @var string
     */
    private string $currency;

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        $params = http_build_query([
            'transaction_mode' => self::TRANSACTION_MODE,
            'transaction_currency' => $this->currency,
            'active' => true,
        ]);

        return 'pa/config/payment_method_types?' . $params;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param ResponseInterface $request
     *
     * @return array
     * @throws \JsonException
     */
    protected function parseResponse(ResponseInterface $request): array
    {
        $response = $this->parseJson($request);

        return array_map(static function (object $item) {
            return $item->name;
        }, $response->items);
    }
}
