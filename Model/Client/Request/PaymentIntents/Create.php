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
use Airwallex\Payments\Model\PaymentConsents;
use JsonException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
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
        $customer = $quote->getCustomer();

        $params = [
            'amount' => $quote->getGrandTotal(),
            'currency' => $quote->getQuoteCurrencyCode(),
            'merchant_order_id' => $quote->getReservedOrderId(),
            'supplementary_amount' => 1,
            'return_url' => $returnUrl,
            'order' => [
                'products' => array_values(array_filter($this->getQuoteProducts($quote))),
                'shipping' => $this->getShippingAddress($quote)
            ]
        ];

        if ($customer
            && $airwallexCustomerIdAttr = $customer->getCustomAttribute(PaymentConsents::KEY_AIRWALLEX_CUSTOMER_ID)) {
            if ($airwallexCustomerIdAttr->getValue()) {
                $params['customer_id'] = $airwallexCustomerIdAttr->getValue();
            }
        }

        return $this->setParams($params);
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
    private function getShippingAddress(Quote $quote): ?array
    {
        $shippingAddress = $quote->getShippingAddress();

        if ($quote->getIsVirtual()) {
            return null;
        }

        return [
            'fist_name' => $shippingAddress->getName(),
            'last_name' => $shippingAddress->getLastname(),
            'phone_number' => $shippingAddress->getTelephone(),
            'shipping_method' => $shippingAddress->getShippingMethod(),
            'address' => [
                'city' => $shippingAddress->getCity(),
                'country_code' => $shippingAddress->getCountryId(),
                'postcode' => $shippingAddress->getPostcode(),
                'state' => $shippingAddress->getRegion(),
                'street' => current($shippingAddress->getStreet()),
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
}
