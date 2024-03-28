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

class CreateCustomer extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/customers/create';
    }

    /**
     * @param string $magentoCustomerId
     * @return AbstractClient|CreateCustomer
     */
    public function setMagentoCustomerId(string $magentoCustomerId)
    {
        return $this->setParam('merchant_customer_id', $magentoCustomerId);
    }

    /**
     * @param ResponseInterface $request
     *
     * @return string
     * @throws \JsonException
     */
    protected function parseResponse(ResponseInterface $request): string
    {
        $request = $this->parseJson($request);

        return $request->id;
    }
}
