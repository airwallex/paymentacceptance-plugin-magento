<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Traits\HelperTrait;
use JsonException;
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
     * @param string $returnUrl
     *
     * @return Create
     */
    public function setOrder(Order $order, string $returnUrl): self
    {
        $params = [
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'merchant_order_id' => $order->getIncrementId(),
            'return_url' => $returnUrl,
            'order' => [
                'products' => $this->getProducts($order),
                'shipping' => $this->getShippingAddress($order)
            ]
        ];
        return $this->setParams($params);
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
