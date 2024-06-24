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
 * @license   GNU General Public License ('GPL') v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Psr\Http\Message\ResponseInterface;

class Create extends AbstractClient implements BearerAuthenticationInterface
{
    /**
     * @param Order $order
     * @param string $returnUrl
     *
     * @return AbstractClient|Create
     */
    public function setOrder(Order $order, string $returnUrl): self
    {
        $params = [
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'merchant_order_id' => $order->getIncrementId(),
            'supplementary_amount' => 1,
            'return_url' => $returnUrl,
            'order' => [
                'products' => array_values(array_filter($this->getOrderProducts($order))),
                'shipping' => $this->getShippingAddress($order)
            ]
        ];

        return $this->setParams($params);
    }

    /**
     * @return $this
     */
    public function setAirwallexCustomerId($id)
    {
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
     * @param Order $order
     *
     * @return array|null
     */
    private function getShippingAddress(Order $order): ?array
    {
        if ($order->getIsVirtual()) {
            return null;
        }
        $shippingAddress = $order->getShippingAddress();

        return [
            'first_name' => $shippingAddress->getFirstname(),
            'last_name' => $shippingAddress->getLastname(),
            'phone_number' => $shippingAddress->getTelephone(),
            'shipping_method' => $order->getShippingMethod(),
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
     * @return array
     */
    private function getQuoteProducts(Quote $quote): array
    {
        return array_map(static function (Item $item) {
            if ((float) $item->getPrice() === 0.0) {
                return null;
            }

            $child = $item->getChildren();
            $child = $child ? current($child) : null;
            $name = $child ? $child->getName() : $item->getName();

            return [
                'code' => $item->getSku(),
                'desc' => $name,
                'name' => $name,
                'quantity' => $item->getQty(),
                'sku' => $item->getSku(),
                'unit_price' => $item->getConvertedPrice(),
                'url' => $item->getProduct()->getProductUrl()
            ];
        }, $quote->getAllItems());
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    private function getOrderProducts(Order $order): array
    {
        $products = [];
        foreach ($order->getAllItems() as $item) {
            $products[] = [
                'code' => $item->getSku(),
                'desc' => $item->getDescription(),
                'name' => $item->getName(),
                'quantity' => $item->getQtyOrdered(),
                'sku' => $item->getSku(),
                'unit_price' => $item->getPrice(),
                'url' => $item->getProduct()->getProductUrl()                
            ];
        }
        return $products;
    }
}
