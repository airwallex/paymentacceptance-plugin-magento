<?php

namespace Airwallex\Payments\Model\Traits;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Controller\Redirect\Index;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Client\Request\Account;
use Airwallex\Payments\Model\Client\Request\CurrencySwitcher;
use Airwallex\Payments\Model\Client\Request\GetCurrencies;
use Airwallex\Payments\Model\Client\Request\Log;
use Airwallex\Payments\Model\Client\Request\PaymentMethod\Get;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Airwallex\Payments\Model\Methods\CardMethod;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\OrderService;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use ReflectionClass;

trait HelperTrait
{
    /**
     * Currency switcher
     *
     * @param string $paymentCurrency
     * @param string $targetCurrency
     * @param string $amount
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     */
    public function currencySwitcher(string $paymentCurrency, string $targetCurrency, string $amount): string
    {
        $cacheName = "awx_cw_{$paymentCurrency}_{$targetCurrency}_" . $amount;
        $cache = $this->cache->load($cacheName);
        if ($cache) {
            $arr = json_decode($cache, true);
            if (isset($arr['refresh_at']) && strtotime($arr['refresh_at']) > time()) {
                return $cache;
            }
        }
        $switcher = ObjectManager::getInstance()->get(CurrencySwitcher::class)
            ->setType()
            ->setPaymentCurrency($paymentCurrency)
            ->setTargetCurrency($targetCurrency)
            ->setAmount($amount)
            ->send();
        $this->cache->save($switcher, $cacheName, [], 1800);
        return $switcher;
    }

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
            'fee_amount' => $object->getShippingAmount(),
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
     * @throws GuzzleException
     * @throws JsonException
     */
    public function account(): string
    {
        return ObjectManager::getInstance()->get(Account::class)->send();
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
            ],
        ];
    }

    /**
     * @param Quote $quote
     * @return Order
     */
    public function getOrderByQuote(Quote $quote): Order
    {
        $collection = ObjectManager::getInstance()->get(CollectionFactory::class)->create();
        $collection->addFieldToFilter('quote_id', $quote->getId());
        $collection->setOrder('entity_id', 'DESC');
        /** @var Order $order */
        $order = $collection->getFirstItem();
        return $order;
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
     * @param Order|Quote $model
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    protected function checkIntent(array $intentResponse, $model): void
    {
        $isOrder = $model instanceof Order;

        $currency = $this->getCurrencyCode($model);
        $orderIncrementId = $isOrder ? $model->getIncrementId() : $model->getReservedOrderId();
        $amount = $model->getGrandTotal();

        $paidStatus = [
            PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
            PaymentIntentInterface::INTENT_STATUS_SUCCEEDED
        ];
        $intentAmount = $intentResponse['base_amount'] ?? $intentResponse['amount'];
        $intentCurrency = $intentResponse['base_currency'] ?? $intentResponse['currency'];
        if (
            !in_array($intentResponse['status'], $paidStatus, true)
            || $intentCurrency !== $currency
            || $intentResponse['merchant_order_id'] !== $orderIncrementId
            || !$this->isAmountEqual(floatval($intentAmount), $amount)
        ) {
            ObjectManager::getInstance()->get(Log::class)->setMessage(
                'check intent failed',
                "Intent Status: {$intentResponse['status']}. Intent Order ID: {$intentResponse['merchant_order_id']} - Order ID: $orderIncrementId - "
                    . "Intent Currency: $intentCurrency - Order Currency: $currency - "
                    . "Intent Amount: $intentAmount - Order Amount: $amount",
                $intentResponse['merchant_order_id']
            )->send();
            $msg = 'Something went wrong while processing your request.';
            throw new Exception(__($msg));
        }
    }

    /**
     * @param $intentResponse
     * @param Order $order
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function checkCardDetail($intentResponse, Order $order): void
    {
        if (!ObjectManager::getInstance()->get(Configuration::class)->isPreVerificationEnabled()) return;
        $lastPaymentType = $intentResponse['latest_payment_attempt']['payment_method']['type'] ?? '';
        if ($lastPaymentType === 'card') {
            $record = $this->paymentIntentRepository->getByIntentId($intentResponse['id']);
            $detail = $record->getDetail();

            $isSame = true;
            $detailArray = $detail ? json_decode($detail, true) : [];
            if (!empty($detailArray['payment_method_ids'])) {
                $paymentMethodGet = ObjectManager::getInstance()->get(Get::class);
                $response = $paymentMethodGet->setPaymentMethodId(end($detailArray['payment_method_ids']))->send();
                $paymentMethodResponse = json_decode($response, true);

                $intentCard = $intentResponse['latest_payment_attempt']['payment_method']['card'] ?? null;
                $card = $paymentMethodResponse['card'] ?? null;

                if (
                    !$intentCard || !$card || $intentCard['bin'] !== $card['bin']
                    || $intentCard['expiry_month'] !== $card['expiry_month']
                    || $intentCard['expiry_year'] !== $card['expiry_year']
                ) {
                    $isSame = false;
                }
            } else {
                $isSame = false;
            }
            if (!$isSame) {
                $this->addComment($order, 'The card information used for the final payment does not match the card information filled in prior to the final payment.');
            }
        }
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
            $this->orderResource->load($latestOrder, $order->getId());
            $this->addComment($latestOrder, $log);
        } catch (Exception $e) {
        }
    }

    /**
     * @throws Exception
     */
    public function addComment(Order $order, string $comment)
    {
        ObjectManager::getInstance()->get(HistoryFactory::class)->create()
            ->setParentId($order->getId())
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
     * @return void
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws NoSuchEntityException
     */
    public function changeOrderStatus($intentResponse, int $orderId, Quote $quote): void
    {
        $seconds = 5;
        $lockKey = 'airwallex_change_order_status_' . $orderId;
        if ($this->cache->load($lockKey)) {
            sleep($seconds);
        }
        $this->cache->save('locked', $lockKey, [], $seconds);
        try {
            $order = $this->getFreshOrder($orderId);
            /** @var Payment $payment */
            $payment = $order->getPayment();
            if ($payment && $payment->getAmountAuthorized() > 0 && $intentResponse['status'] === PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE) {
                return;
            }
            if ($order->getTotalPaid() > 0) return;
            $this->checkIntent($intentResponse, $order);
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

            $order->place();
            ObjectManager::getInstance()->get(OrderRepository::class)->save($order);

            $this->addAVSResultToOrder($order, $intentResponse);

            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);

            try {
                $this->checkCardDetail($intentResponse, $order);
            } catch (Exception $e) {
            }
        } finally {
            $this->cache->remove($lockKey);
        }
    }

    public function getFreshOrder(int $orderId)
    {
        $order = ObjectManager::getInstance()->get(OrderFactory::class)->create();
        ObjectManager::getInstance()->get(OrderResourceInterface::class)->load($order, $orderId);
        return $order;
    }

    /**
     * @throws CouldNotSaveException
     * @throws AlreadyExistsException
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws InputException
     */
    public function placeOrder($intentResponse, Quote $quote, string $from = '', $billingAddress = null)
    {
        $quoteId = $quote->getId();
        $seconds = 5;
        $lockKey = 'airwallex_place_order_' . $quoteId;
        if ($this->cache->load($lockKey)) {
            sleep($seconds);
        }
        $this->cache->save('locked', $lockKey, [], 10);
        try {
            if (!$quote->getCustomerId()) {
                $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
            }
            try {
                $order = ObjectManager::getInstance()->get(OrderInterface::class)->loadByAttribute('increment_id', $intentResponse['merchant_order_id']);
            } catch (Exception $e) {
            }
            if (empty($order) || empty($order->getId())) {
                $this->checkIntent($intentResponse, $quote);
                $resp = $this->intentGet->setPaymentIntentId($intentResponse['id'])->send();
                $intentResponse = json_decode($resp, true);
                $this->intentHelper->setIntent($intentResponse);
                if ($from !== OrderService::class && $from !== Index::class) {
                    $quote->setTotalsCollectedFlag(true);
                    $orderId = ObjectManager::getInstance()->get(CartManagementInterface::class)->placeOrder($quote->getId());
                } else {
                    if ($quote->getCustomerId()) {
                        if ($quote->getPayment()->getMethod() === Vault::CODE) {
                            $quote->getPayment()->setMethod(CardMethod::CODE);
                        }
                        $orderId = ObjectManager::getInstance()->get(PaymentInformationManagementInterface::class)->savePaymentInformationAndPlaceOrder(
                            $quoteId,
                            $quote->getPayment(),
                            $billingAddress
                        );
                    } else {
                        $cartId = ObjectManager::getInstance()->get(QuoteIdToMaskedQuoteIdInterface::class)->execute($quoteId);
                        $orderId = ObjectManager::getInstance()->get(GuestPaymentInformationManagementInterface::class)->savePaymentInformationAndPlaceOrder(
                            $cartId,
                            $intentResponse['customer']['email'],
                            $quote->getPayment(),
                            $billingAddress
                        );
                    }
                }

                $paymentIntentRecord = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getByIntentId($intentResponse['id']);
                ObjectManager::getInstance()->get(PaymentIntentRepository::class)->updateOrderId($paymentIntentRecord, $orderId);
                $order = $this->getFreshOrder($orderId);
                if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT && !ObjectManager::getInstance()->get(Configuration::class)->isOrderBeforePayment()) {
                    $this->setCheckoutSuccess($quoteId, $order);
                }

                $this->addAVSResultToOrder($order, $intentResponse);
                try {
                    $this->checkCardDetail($intentResponse, $order);
                } catch (Exception $e) {
                }
            } else {
                if ($quote->getIsActive()) {
                    $quote->setIsActive(false);
                    $this->quoteRepository->save($quote);
                }
            }
        } finally {
            $this->cache->remove($lockKey);
        }
    }

    public function setCheckoutSuccess($quoteId, $order)
    {
        $checkoutHelper = ObjectManager::getInstance()->get(Data::class);
        $checkoutHelper->getCheckout()->setLastQuoteId($quoteId);
        $checkoutHelper->getCheckout()->setLastSuccessQuoteId($quoteId);
        $checkoutHelper->getCheckout()->setLastOrderId($order->getId());
        $checkoutHelper->getCheckout()->setLastRealOrderId($order->getIncrementId());
        $checkoutHelper->getCheckout()->setLastOrderStatus($order->getStatus());
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

    public function getCurrencyCode($model)
    {
        if ($model instanceof Quote) {
            return $model->getQuoteCurrencyCode();
        }
        return $model->getOrderCurrencyCode();
    }


    /**
     * @throws GuzzleException
     */
    public function getAvailableCurrencies()
    {
        $cacheName = 'airwallex_available_currencies';
        $result = $this->cache->load($cacheName);
        if (empty($result) || $result === "[]") {
            $index = 0;
            $items = [];
            while (true) {
                try {
                    $res = ObjectManager::getInstance()->get(GetCurrencies::class)
                        ->setPage($index, 200)
                        ->send();
                } catch (Exception $exception) {
                    return json_encode($items);
                }

                $index++;
                if (!empty($res['items'])) {
                    $items = array_merge($items, $res['items']);
                }
                if (!$res['has_more']) {
                    break;
                }
            }
            $result = json_encode($items);
            $this->cache->save($result, $cacheName, [], 300);
        }
        return $result;
    }

    public function isOrderBeforePayment(): bool
    {
        return ObjectManager::getInstance()->get(Configuration::class)->isOrderBeforePayment();
    }
}
