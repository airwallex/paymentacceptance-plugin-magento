<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class Confirm extends AbstractClient implements BearerAuthenticationInterface
{
    private const DESKTOP_FLOW = 'webqr';
    private const MOBILE_FLOW = 'mweb';
    private const WECHAT_FLOW = 'jsapi';

    /**
     * @var string
     */
    private string $paymentIntentId;

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

        if ($method !== 'pay_now') {
            $data[$method] = [
                'flow' => 'qrcode'
            ];
        }

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
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $response = $this->parseJson($response);

        return (array)$response->next_action ?? [];
    }
}
