<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
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
     * @throws LocalizedException
     */
    public function setOrder(Order $order, PaymentInterface $paymentMethod, string $returnUrl): self
    {
        $products = $this->getProducts($order);
        $products[] = [
            'code' => 0,
            'name' => 'fake-product',
            'quantity' => 1,
            'sku' => 'fake-product',
            'unit_price' => $this->getAmount($order, $paymentMethod),
        ];
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
     * @throws LocalizedException
     */
    public function getCurrency(Order $order, PaymentInterface $paymentMethod)
    {
        if ($paymentMethod->getMethod() !== KlarnaMethod::CODE) return $order->getOrderCurrencyCode();
        $this->testPaymentMethod($order);
        $country = $order->getBillingAddress()->getCountryId();
        return KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country];
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
     * @throws LocalizedException
     * @throws Exception
     */
    public function getAmount(Order $order, PaymentInterface $paymentMethod)
    {
        if ($paymentMethod->getMethod() !== KlarnaMethod::CODE) return $order->getGrandTotal();
        $this->testPaymentMethod($order);
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
