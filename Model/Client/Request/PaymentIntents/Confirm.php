<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
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
        if (empty(KlarnaMethod::COUNTRY_LANGUAGE[$countryCode])) {
            return 'en';
        }
        if (in_array($lang, KlarnaMethod::COUNTRY_LANGUAGE[$countryCode], true)) {
            return $lang;
        }
        return 'en';
    }

    /**
     * @param string $method
     * @param AddressInterface|OrderAddressInterface|null $address
     * @param string $email
     * @return Confirm
     */
    public function setInformation(string $method, $address = null, string $email = ""): self
    {
        $data = [
            'type' => $method,
        ];

        if (in_array($method, ['klarna', 'afterpay'], true)) {
            $countryCode = $address->getCountryId();
            $data[$method] = [
                'country_code' => $countryCode,
                'shopper_email' => $email ?: $address->getEmail(),
                'billing' => [
                    'address' => [
                        "country_code" => $countryCode,
                        "street" => $address->getStreet() ? implode(', ', $address->getStreet()) : '',
                        "city" => $address->getCity(),
                        'state' => $address->getRegionCode(),
                        'postcode' => $address->getPostcode(),
                    ],
                    'email' => $email ?: $address->getEmail(),
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

    public function setQuote(string $targetCurrency, string $quoteId)
    {
        return $this->setParams([
            'currency_switcher' => [
                'target_currency' => $targetCurrency,
                'quote_id' => $quoteId
            ],
        ]);
    }

    public function setBrowserInformation(string $info)
    {
        if (!$info) return $this;
        return $this->setParams(json_decode($info, true));
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
     * @throws JsonException|LocalizedException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $response = $this->parseJson($response);
        if (empty($response->next_action) && !empty($response->message)) {
            throw new LocalizedException(__($response->message));
        }
        return (array)$response->next_action;
    }
}
