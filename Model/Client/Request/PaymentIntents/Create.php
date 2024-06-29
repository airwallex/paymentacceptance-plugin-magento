<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Magento\Quote\Model\Quote;
use Psr\Http\Message\ResponseInterface;

class Create extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @param Quote $quote
     * @param string $returnUrl
     *
     * @return AbstractClient|Create
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
                'products' => $this->getQuoteProducts($quote),
                'shipping' => $this->getShippingAddress($quote)
            ]
        ];
        return $this->setParams($params);
    }

    /**
     * @return $this
     */
    public function setAirwallexCustomerId(string $id)
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
     * @param ResponseInterface $request
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $request): array
    {
        $data = $this->parseJson($request);

        return [
            'clientSecret' =>  $data->client_secret,
            'id' =>  $data->id,
        ];
    }

    /**
     * @param Quote $quote
     *
     * @return array|null
     */
    public function getShippingAddress(Quote $quote): ?array
    {
        $shippingAddress = $quote->getShippingAddress();

        if ($quote->getIsVirtual()) {
            return null;
        }

        return [
            'first_name' => $shippingAddress->getFirstname(),
            'last_name' => $shippingAddress->getLastname(),
            'phone_number' => $shippingAddress->getTelephone(),
            'shipping_method' => $shippingAddress->getShippingMethod(),
            'address' => [
                'city' => $shippingAddress->getCity(),
                'country_code' => $shippingAddress->getCountryId(),
                'postcode' => $shippingAddress->getPostcode(),
                'state' => $shippingAddress->getRegion(),
                'street' => implode(', ', $shippingAddress->getStreet()),
            ]
        ];
    }

    /**
     * @param Quote $quote
     *
     * @return array|null
     */
    public function getBillingAddress(Quote $quote): ?array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'first_name' => $billingAddress->getFirstname(),
            'last_name' => $billingAddress->getLastname(),
            'phone_number' => $billingAddress->getTelephone(),
            'address' => [
                'city' => $billingAddress->getCity(),
                'country_code' => $billingAddress->getCountryId(),
                'postcode' => $billingAddress->getPostcode(),
                'state' => $billingAddress->getRegion(),
                'street' => implode(', ', $billingAddress->getStreet()),
            ]
        ];
    }

    /**
     * @param Quote $quote
     *
     * @return array
     */
    public function getQuoteProducts(Quote $quote): array
    {
        $products = [];
        foreach ($quote->getAllItems() as $item) {
            $product = $item->getProduct();
            $products[] = [
                'code' => $product ? $product->getId() : '',
                'name' => $item->getName() ?: '',
                'quantity' => intval($item->getQty()),
                'sku' => $item->getSku() ?: '',
                'unit_price' => $item->getConvertedPrice(),
                'url' => $product ? $product->getProductUrl() : '',
                'type' => $product ? $product->getTypeId() : '',
            ];
        }
        return $products;
    }
}
