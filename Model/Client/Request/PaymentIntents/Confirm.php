<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\BankTransfer;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Psr\Http\Message\ResponseInterface;
use Detection\MobileDetect;

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
     * @param array $intent
     * @param array $currencySwitcherData
     * @return Confirm
     * @throws LocalizedException
     */
    public function setInformation(string $method, $address, string $email = "", array $intent = [], array $currencySwitcherData = []): self
    {
        $data = [
            'payment_method' => [
                'type' => $method,
            ]
        ];

        if (in_array($method, ['klarna', 'afterpay', 'bank_transfer'], true)) {
            if (empty($address)) {
                throw new LocalizedException(__('Billing address cannot be empty.'));
            }
            if (empty($intent['currency'])) {
                throw new LocalizedException(__('Intent currency cannot be empty.'));
            }
            $data['payment_method'][$method] = [
                'shopper_email' => $email ?: $address->getEmail(),
                'billing' => [
                    'address' => [
                        "country_code" => $address->getCountryId(),
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
                "shopper_name" => $address->getFirstName() . ' ' . $address->getLastname(),
                'language' => $this->getLanguageCode($address->getCountryId(), $method),
            ];
            $data['payment_method_options'][$method]['auto_capture'] = $this->configuration->isAutoCapture($method);
            if ($method === 'klarna') {
                $data['payment_method'][$method]['country_code'] = $address->getCountryId();
            } else if ($method === 'bank_transfer') {
                $currencyCollection = BankTransfer::SUPPORTED_CURRENCY_TO_COUNTRY;
                if (!empty($currencySwitcherData['target_currency'])) {
                    if (empty($currencyCollection[$currencySwitcherData['target_currency']])) {
                        throw new LocalizedException(__('Invalid currency for Bank Transfer'));
                    }
                    $data['payment_method'][$method]['country_code'] = $currencyCollection[$currencySwitcherData['target_currency']];
                } else {
                    if (empty($currencyCollection[$intent['currency']])) {
                        throw new LocalizedException(__('Invalid currency for Bank Transfer'));
                    }
                    $data['payment_method'][$method]['country_code'] = $currencyCollection[$intent['currency']];
                }
            }
        }

        $data = $this->setFlow($data, $method);
        return $this->setParams($data);
    }

    public function setFlow(array $data, $paymentMethod): array
    {
        $flow = 'qrcode';
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $detect = new MobileDetect();
            $detect->setUserAgent($_SERVER['HTTP_USER_AGENT']);
            $data['device_data']['browser'] = [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'javascript_enabled' => true,
            ];
            if ($detect->isMobile() && !in_array($paymentMethod, ['klarna', 'afterpay', 'pay_now'], true)) {
                $data['payment_method'][$paymentMethod]['os_type'] = $detect->isAndroidOS() ? 'android' : 'ios';
                $flow = 'mobile_web';
            }
        }
        $data['payment_method'][$paymentMethod]['flow'] = $flow;
        return $data;
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
