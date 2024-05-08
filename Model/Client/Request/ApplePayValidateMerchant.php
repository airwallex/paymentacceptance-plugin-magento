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

class ApplePayValidateMerchant extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_session/start';
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return 'POST';
    }

    /**
     * @param ResponseInterface $request
     *
     * @return string
     */
    protected function parseResponse(ResponseInterface $request): string
    {
        return $request->getBody();
    }

    public function setInitiativeParams($initiative): AbstractClient
    {
        $this->setParams($initiative);
        return $this;
    }
}
