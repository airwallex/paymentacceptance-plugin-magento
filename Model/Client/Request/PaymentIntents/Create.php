<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\CurrentPaymentMethodHelper;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\PaymentIntents;
use Airwallex\Payments\Model\Traits\HelperTrait;
use JsonException;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Setup\Console\InputValidationException;
use Psr\Http\Message\ResponseInterface;

class Create extends AbstractClient implements BearerAuthenticationInterface
{
    use HelperTrait;

    protected function getProductsForIntentCreate($model, PaymentInterface $paymentMethod, array $products): array
    {
        if ($paymentMethod->getMethod() === KlarnaMethod::CODE) {
            $products[] = [
                'code' => 0,
                'name' => 'Other Fees',
                'quantity' => 1,
                'sku' => '',
                'unit_price' => $model->getGrandTotal(),
            ];
        }
        return $products;
    }

    public function setIntentParams($model, PaymentInterface $paymentMethod, string $returnUrl): self
    {
        $isOrder = $model instanceof Order;

        if (!$isOrder && !$model->getReservedOrderId()) {
            $model->reserveOrderId();
            ObjectManager::getInstance()->get(QuoteRepository::class)->save($model);
        }

        $products = $this->getProducts($model);
        $merchantOrderId = $isOrder ? $model->getIncrementId() : $model->getReservedOrderId();
        $params = [
            'amount' => round($model->getGrandTotal(), PaymentIntents::CURRENCY_TO_DECIMAL[$this->getCurrencyCode($model)] ?? 2),
            'currency' => $this->getCurrencyCode($model),
            'merchant_order_id' => $merchantOrderId,
            'platform_payment_id' => "",
            'platform_order_id' => $merchantOrderId,
            'platform_order_name' => $merchantOrderId,
            'return_url' => trim($returnUrl, '/'),
            'order' => [
                'products' => $this->getProductsForIntentCreate($model, $paymentMethod, $products),
                'shipping' => $this->getShippingAddress($model)
            ]
        ];
        return $this->setParams($params);
    }

    protected function getReferrerData(): array
    {
        $method = ObjectManager::getInstance()->get(CurrentPaymentMethodHelper::class)->getPaymentMethod();
        $method = $this->trimPaymentMethodCode($method);
        $method = ($method === 'card' || $method === 'vault') ? 'credit_card' : $method;
        return [
            'type' => 'magento_' . $method,
            'version' => $this->moduleList->getOne(Configuration::MODULE_NAME)['setup_version']
        ];
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

        if (!empty($data->code) && $data->code === 'validation_error') {
            throw new InputValidationException(__($data->message));
        }

        return [
            'clientSecret' => $data->client_secret ?? '',
            'id' => $data->id ?? '',
            'amount' => $data->amount ?? 0,
            'currency' => $data->currency ?? '',
        ];
    }
}
