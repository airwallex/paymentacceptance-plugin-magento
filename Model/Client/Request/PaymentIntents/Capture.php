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

class Capture extends AbstractClient implements BearerAuthenticationInterface
{
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
     * @param float $amount
     *
     * @return Capture
     */
    public function setInformation(float $amount): self
    {
        return $this->setParams([
            'amount' => $amount,
        ]);
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . $this->paymentIntentId . '/capture';
    }

    /**
     * @param ResponseInterface $request
     *
     * @return object
     * @throws \JsonException
     */
    protected function parseResponse(ResponseInterface $request): object
    {
        return $this->parseJson($request);
    }
}
