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
namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Psr\Http\Message\ResponseInterface;

class Confirm extends AbstractClient implements BearerAuthenticationInterface
{
    private const DESKTOP_FLOW = 'webqr';
    private const MOBILE_FLOW = 'mweb';

    /**
     * @var string
     */
    private $paymentIntentId;

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setPaymentIntentId(string $id): self
    {
        $this->paymentIntentId = $id;

        return $this;
    }

    /**
     * @param string $method
     * @param bool $isMobile
     * @param string|null $osType
     *
     * @return Confirm
     */
    public function setInformation(string $method, bool $isMobile, string $osType = null): self
    {
        $data = [
            'type' => $method
        ];

        $data[$method] = [
            'flow' => $isMobile ? self::MOBILE_FLOW : self::DESKTOP_FLOW
        ];

        if ($isMobile) {
            $data[$method]['os_type'] = $osType;
        }

        return $this->setParams([
            'payment_method' => $data
        ]);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . $this->paymentIntentId . '/confirm';
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

        return $request->next_action->url;
    }
}
