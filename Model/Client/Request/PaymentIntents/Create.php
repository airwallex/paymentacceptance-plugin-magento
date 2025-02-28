<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentIntents;

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
        $params = [
            'amount' => round($model->getGrandTotal(), PaymentIntents::CURRENCY_TO_DECIMAL[$this->getCurrencyCode($model)]),
            'currency' => $this->getCurrencyCode($model),
            'merchant_order_id' => $isOrder ? $model->getIncrementId() : $model->getReservedOrderId(),
            'return_url' => trim($returnUrl, '/'),
            'order' => [
                'products' => $this->getProductsForIntentCreate($model, $paymentMethod, $products),
                'shipping' => $this->getShippingAddress($model)
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
