<?php

namespace Airwallex\Payments\Model\Traits;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use ReflectionClass;

trait HelperTrait
{
    public function convertToDisplayCurrency(float $amount, $rate, $reverse = false): float
    {
        if (empty($rate)) {
            return $amount;
        }
        if ($reverse) {
            return round($amount / $rate, 4);
        }
        return round($amount * $rate, 4);
    }

    /**
     * @param Payment $payment
     * @param string $intentId
     */
    protected function setTransactionId(Payment $payment, string $intentId)
    {
        $payment->setTransactionId($intentId);
        $payment->setIsTransactionClosed(false);
    }

    public function getBaseAmount(float $amount, float $rate, float $amountMax, float $baseAmountMax): float
    {
        $baseAmount = $this->convertToDisplayCurrency($amount, $rate, true);
        if ($this->isAmountEqual($amount, $amountMax) || $baseAmount - $baseAmountMax >= 0.0001) {
            $baseAmount = $baseAmountMax;
        }
        return $baseAmount;
    }

    public function convertCcType(string $type): string
    {
        if (strtolower($type) === 'jcb') {
            return 'jcb';
        }
        if (strtolower($type) === 'visa') {
            return 'vi';
        }
        if (strtolower($type) === 'discover') {
            return 'di';
        }
        if (in_array(strtolower($type), ['diners', 'diners club international'])) {
            return 'dn';
        }
        if (in_array(strtolower($type), ['amex', 'american express', 'americanexpress'])) {
            return 'ae';
        }
        if (in_array(strtolower($type), ['unionpay', 'up', 'union pay'])) {
            return 'un';
        }
        if (in_array(strtolower($type), ['mastercard', 'master card'])) {
            return 'mc';
        }
        return strtolower($type);
    }

    public function isAmountEqual(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    public function isRedirectMethodConstant($string): bool
    {
        $reflectionClass = new ReflectionClass('Airwallex\Payments\Model\Methods\RedirectMethod');
        $constants = $reflectionClass->getConstants();

        return in_array($string, $constants);
    }

    /**
     * @param Quote|Order $object
     *
     * @return array|null
     */
    public function getShippingAddress($object): ?array
    {
        $shippingAddress = $object->getShippingAddress();

        if ($object->getIsVirtual()) {
            return null;
        }

        $method = ($object instanceof Order) ? $object->getShippingMethod() : $shippingAddress->getShippingMethod();
        return [
            'first_name' => $shippingAddress->getFirstname(),
            'last_name' => $shippingAddress->getLastname(),
            'phone_number' => $shippingAddress->getTelephone(),
            'shipping_method' => $method,
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
     * @param Quote|Order $object
     *
     * @return array
     */
    public function getProducts($object): array
    {
        $products = [];
        foreach ($object->getAllItems() as $item) {
            $product = $item->getProduct();
            $qty = ($object instanceof Order) ? $item->getQtyOrdered() : $item->getQty();
            $products[] = [
                'code' => $product ? $product->getId() : '',
                'name' => $item->getName() ?: '',
                'quantity' => intval($qty),
                'sku' => $item->getSku() ?: '',
                'unit_price' => $item->getPrice(),
                'url' => $product ? $product->getProductUrl() : '',
                'type' => $product ? $product->getTypeId() : '',
            ];
        }
        return $products;
    }

    /**
     * @param Quote|Order $object
     *
     * @return array|null
     */
    public function getBillingAddress($object): ?array
    {
        $billingAddress = $object->getBillingAddress();

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
     * @return Order
     */
    public function getOrderByQuote(Quote $quote): Order
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('quote_id', $quote->getId());
        $collection->setOrder('entity_id', 'DESC');
        /** @var Order $order */
        $order = $collection->getFirstItem();
        return $order;
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    public function checkIntent(
        string $status,
        string $intentCurrency,
        string $currency,
        string $intentOrderId,
        string $orderIncrementId,
        float  $intentAmount,
        float  $amount
    )
    {
        $paidStatus = [
            PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
            PaymentIntentInterface::INTENT_STATUS_SUCCEEDED
        ];
        if (
            !in_array($status, $paidStatus, true)
            || $intentCurrency !== $currency
            || $intentOrderId !== $orderIncrementId
            || !$this->isAmountEqual($intentAmount, $amount)) {
            $this->errorLog->setMessage('check intent failed'
                , "Intent Order ID: $intentOrderId - Quote Order ID: $orderIncrementId - "
                . "Intent Currency: $intentCurrency - Quote Currency: $currency - "
                . "Intent Amount: $intentAmount - Quote Amount: $amount", $intentOrderId)->send();
            $msg = 'Something went wrong while processing your request.';
            throw new Exception(__($msg));
        }
    }

    public function captureCacheName(string $intentId): string
    {
        return $intentId . '_capture';
    }

    public function refundCacheName(string $intentId): string
    {
        return $intentId . '_refund';
    }

    public function cancelCacheName(string $intentId): string
    {
        return $intentId . '_cancel';
    }

    /**
     * Check intent status if available to change order status
     *
     * @param array $intentResponse
     * @param Order $order
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function checkIntentWithOrder(array $intentResponse, Order $order): void
    {
        $this->checkIntent(
            $intentResponse['status'],
            $intentResponse['currency'],
            $order->getOrderCurrencyCode(),
            $intentResponse['merchant_order_id'],
            $order->getIncrementId(),
            floatval($intentResponse['amount']),
            $order->getGrandTotal(),
        );
    }

    protected function error($message)
    {
        return json_encode([
            'type' => 'error',
            'message' => $message
        ]);
    }

    protected function addAVSResultToOrder(Order $order, array $intentResponse)
    {
        $histories = $order->getStatusHistories();

        $log = $src = '[Verification] ';
        if ($histories) {
            foreach ($histories as $history) {
                if (!$history->getComment()) continue;
                if (strstr($history->getComment(), $log)) return;
            }
        }
        try {
            $brand = $intentResponse['latest_payment_attempt']['payment_method']['card']['brand'] ?? '';
            if ($brand) $brand = ' Card Brand: ' . strtoupper($brand) . '.';
            $last4 = $intentResponse['latest_payment_attempt']['payment_method']['card']['last4'] ?? '';
            if ($last4) $last4 = ' Card Last Digits: ' . $last4 . '.';
            $avs_check = $intentResponse['latest_payment_attempt']['authentication_data']['avs_result'] ?? '';
            if ($avs_check) $avs_check = ' AVS Result: ' . $avs_check . '.';
            $cvc_check = $intentResponse['latest_payment_attempt']['authentication_data']['cvc_result'] ?? '';
            if ($cvc_check) $cvc_check = ' CVC Result: ' . $cvc_check . '.';
            $log .= $brand . $last4 . $avs_check . $cvc_check;
            if ($log === $src) return;
            $latestOrder = $this->orderFactory->create();
            $this->orderResource->load($latestOrder, $order->getEntityId());
            $this->addComment($latestOrder, $log);
        } catch (Exception $e) {
        }
    }

    public function addComment(Order $order, string $comment)
    {
        ObjectManager::getInstance()->get(HistoryFactory::class)->create()
            ->setParentId($order->getEntityId())
            ->setComment(__($comment))
            ->setEntityName('order')
            ->setStatus($order->getStatus())
            ->save();
    }

    public function totalPriceForComment(Order $order): string
    {
        return $this->priceForComment($order->getGrandTotal(), $order->getBaseGrandTotal(), $order);
    }

    public function priceForComment($price, $basePrice, $order): string
    {
        $formatPrice = $order->formatPrice($price);
        $formatBasePrice = $order->formatBasePrice($basePrice);
        if ($formatPrice !== $formatBasePrice) {
            return "$formatBasePrice($formatPrice)";
        }
        return $formatBasePrice;
    }

    /**
     * @param $intentResponse
     * @param int $orderId
     * @param Quote $quote
     * @param string $from
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    public function changeOrderStatus($intentResponse, int $orderId, Quote $quote, string $from = ''): void
    {
        $seconds = 5;
        $lockKey = 'airwallex_change_order_status_' . $orderId;
        if ($this->cache->load($lockKey)) {
            sleep($seconds);
        }
        $this->cache->save('locked', $lockKey, [], $seconds);
        try {
            $order = $this->orderFactory->create();
            $this->orderResource->load($order, $orderId);
            $payment = $order->getPayment();
            if ($payment && $payment->getAmountAuthorized() > 0 && $intentResponse['status'] === PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE) {
                return;
            }
            if ($order->getTotalPaid() > 0) return;
            $this->checkIntentWithOrder($intentResponse, $order);
            $this->setTransactionId($order->getPayment(), $intentResponse['id']);
            $this->intentHelper->setIntent($intentResponse);
            if ($this->isMiniPluginExists()) {
                $companyOrder = ObjectManager::getInstance()->get('\Magento\Company\Api\Data\CompanyOrderInterfaceFactory')->create();
                $companyResource = ObjectManager::getInstance()->get('\Magento\Company\Model\ResourceModel\Order');
                $companyResource->load($companyOrder, $order->getId(), 'order_id');
                if ($companyOrder && $companyOrder->getId()) {
                    $companyResource->delete($companyOrder);
                }
            }
            $this->orderManagement->place($order);
            $this->addAVSResultToOrder($order, $intentResponse);
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        } finally {
            $this->cache->remove($lockKey);
        }
    }

    /**
     * @param $code
     * @return string
     */
    protected function getPaymentMethodCode($code): string
    {
        return str_replace(AbstractMethod::PAYMENT_PREFIX, '', $code);
    }

    public function isMiniPluginExists(): bool
    {
        return file_exists('../app/code/airwallex/paymentacceptance-minifeature-magento-admin-card/Model/CompanyConsents.php');
    }
}
