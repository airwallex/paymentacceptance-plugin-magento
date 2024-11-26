<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use JsonException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Psr\Http\Message\ResponseInterface;

class Confirm extends AbstractClient implements BearerAuthenticationInterface
{
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

    private function getLanguageCode($countryCode, $method): string
    {
        if (!in_array($method, ['klarna', 'afterpay'], true)) return 'en';
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return 'en';
        $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $lang = $languages[0];
        if ($lang === 'zh-TW') {
            $lang = 'zh-HK';
        }
        if ($method === 'afterpay') {
            return $lang;
        }
        if (in_array($lang, KlarnaMethod::COUNTRY_LANGUAGE[$countryCode], true)) {
            return $lang;
        }
        return 'en';
    }

    /**
     * @param string $method
     * @param OrderAddressInterface|null $address
     * @return Confirm
     */
    public function setInformation(string $method, OrderAddressInterface $address = null): self
    {
        $data = [
            'type' => $method,
        ];

        if (in_array($method, ['klarna', 'afterpay'], true)) {
            $countryCode = $address->getCountryId();
            $data[$method] = [
                'country_code' => $countryCode,
                'shopper_email' => $address->getEmail(),
                'billing' => [
                    'address' => [
                        "country_code" => $countryCode,
                        "street" => $address->getStreet() ? implode(', ', $address->getStreet()) : '',
                        "city" => $address->getCity(),
                        'state' => $address->getRegionCode(),
                        'postcode' => $address->getPostcode(),
                    ],
                    'email' => $address->getEmail(),
                    'first_name' => $address->getFirstName(),
                    'last_name' => $address->getLastname(),
                    "phone_number" => $address->getTelephone(),
                ],
                "shopper_phone" => $address->getTelephone(),
                'language' => $this->getLanguageCode($countryCode, $method),
                'flow' => 'qrcode',
            ];
        } else if ($method !== 'pay_now') {
            $data[$method] = [
                'flow' => 'qrcode'
            ];
        }

        return $this->setParams([
            'payment_method' => $data,
            'payment_method_options' => [
                'klarna' => [
                    'auto_capture' => $this->configuration->isKlarnaCaptureEnabled(),
                ]
            ],
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
