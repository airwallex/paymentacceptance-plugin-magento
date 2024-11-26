<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\AfterpayMethod;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\Methods\RedirectMethod;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Psr\Http\Message\ResponseInterface;

class Create extends AbstractClient implements BearerAuthenticationInterface
{
    use HelperTrait;

    /**
     * @param Quote $quote
     * @param string $returnUrl
     *
     * @return Create
     */
    public function setQuote(Quote $quote, string $returnUrl): self
    {
        $params = [
            'amount' => $quote->getGrandTotal(),
            'currency' => $quote->getQuoteCurrencyCode(),
            'merchant_order_id' => $quote->getReservedOrderId(),
            'supplementary_amount' => 1,
            'return_url' => $returnUrl,
            'order' => [
                'products' => $this->getProducts($quote),
                'shipping' => $this->getShippingAddress($quote)
            ]
        ];
        return $this->setParams($params);
    }

    /**
     * @param Order $order
     * @param PaymentInterface $paymentMethod
     * @param string $returnUrl
     *
     * @return Create
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function setOrder(Order $order, PaymentInterface $paymentMethod, string $returnUrl): self
    {
        $products = $this->getProducts($order);
        if ($paymentMethod->getMethod() === KlarnaMethod::CODE) {
            $products[] = [
                'code' => 0,
                'name' => 'fake-product',
                'quantity' => 1,
                'sku' => 'fake-product',
                'unit_price' => $this->getAmount($order, $paymentMethod),
            ];
        }
        $params = [
            'amount' => $this->getAmount($order, $paymentMethod),
            'currency' => $this->getCurrency($order, $paymentMethod),
            'merchant_order_id' => $order->getIncrementId(),
            'return_url' => trim($returnUrl, '/'),
            'order' => [
                'products' => $products,
                'shipping' => $this->getShippingAddress($order)
            ]
        ];
        return $this->setParams($params);
    }

    /**
     * @param Order $order
     * @param PaymentInterface $paymentMethod
     * @return float|string|null
     * @throws JsonException
     * @throws LocalizedException
     * @throws GuzzleException
     */
    public function getCurrency(Order $order, PaymentInterface $paymentMethod)
    {
        if (!in_array($paymentMethod->getMethod(), RedirectMethod::CURRENCY_SWITCHER_METHODS, true)) return $order->getOrderCurrencyCode();
        $country = $order->getBillingAddress()->getCountryId();
        if ($paymentMethod->getMethod() === KlarnaMethod::CODE) {
            $this->testPaymentMethod($order);
            return KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country];
        }
        $account = $this->account();
        $arr = json_decode($account, true);
        $entity = $arr['entity'];
        if (!isset(AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity])) {
            throw new LocalizedException(__('The selected payment method is not supported.'));
        }
        if (in_array($order->getOrderCurrencyCode(), AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity], true)) {
            return $order->getOrderCurrencyCode();
        }
        if (in_array($order->getBaseCurrencyCode(), AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity], true)) {
            return $order->getBaseCurrencyCode();
        }
        if (count(AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity]) === 1) return AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity][0];
        $currency = AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country] ?? '';
        if (empty($currency)) {
            $afterpayCountry = $paymentMethod->getAdditionalData()['afterpay_country'] ?? '';
            if ($afterpayCountry === 'GB') $afterpayCountry = 'UK';
            $currency = AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$afterpayCountry] ?? '';
        }
        if (empty($currency)) {
            throw new LocalizedException(__('The selected afterpay country is not supported.'));
        }
        return $currency;
    }

    /**
     * @throws LocalizedException
     */
    private function testPaymentMethod(Order $order)
    {
        $country = $order->getBillingAddress()->getCountryId();
        if (!in_array($country, array_keys(KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY), true)) {
            throw new LocalizedException(__('Klarna is not available in your country. Please change your billing address to a compatible country or choose a different payment method.'));
        }
    }

    /**
     * @param Order $order
     * @param PaymentInterface $paymentMethod
     * @return float|mixed|null
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function getAmount(Order $order, PaymentInterface $paymentMethod)
    {
        if (!in_array($paymentMethod->getMethod(), RedirectMethod::CURRENCY_SWITCHER_METHODS, true)) return $order->getGrandTotal();
        if ($order->getOrderCurrencyCode() === $this->getCurrency($order, $paymentMethod)) {
            return $order->getGrandTotal();
        }
        if ($order->getBaseCurrencyCode() === $this->getCurrency($order, $paymentMethod)) {
            return $order->getBaseGrandTotal();
        }
        $switcher = $this->currencySwitcher($order->getOrderCurrencyCode(), $this->getCurrency($order, $paymentMethod), $order->getGrandTotal());
        $items = json_decode($switcher, true);
        return $items['target_amount'];
    }

    /**
     * @return $this
     */
    public function setAirwallexCustomerId(string $id): self
    {
        if (empty($id)) return $this;
        return $this->setParam('customer_id', $id);
    }

    /**
     * @return $this
     */
    public function setCustomer(string $email, string $phone): self
    {
        $customer = [];
        if (!empty($email)) $customer['email'] = $email;
        if (!empty($phone)) $customer['phone_number'] = $phone;
        return $this->setParams(compact('customer'));
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/create';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $data = $this->parseJson($response);

        return [
            'clientSecret' => $data->client_secret,
            'id' => $data->id,
        ];
    }
}
