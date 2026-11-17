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
namespace Airwallex\Payments\Model\Traits;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\ConversionQuote as ConversionQuoteUseCase;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\Config\CurrencySwitcherAvailableCurrencies;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Airwallex\Payments\Model\Methods\RedirectMethod;
use Airwallex\Payments\Model\PaymentFlowLog;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethod\Get as RetrievePaymentMethod;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethod as StructPaymentMethod;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Airwallex\Payments\Model\Methods\CardMethod;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Exception\SigningKeyMissingException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\PaymentOperationLock;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent;
use Airwallex\Payments\Model\ReturnState;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\PaymentExtension;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Magento\Framework\DB\TransactionFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Account;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ConversionQuote as StructConversionQuote;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Account as StructAccount;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;

trait HelperTrait
{
    /**
     * Get MCP conversion quote
     *
     * @param string $merchantCurrency
     * @param string $shopperCurrency
     * @return string
     */
    public function conversionQuote(string $merchantCurrency, string $shopperCurrency): string
    {
        $quote = $this->getConversionQuote($merchantCurrency, $shopperCurrency);
        return json_encode([
            'id' => $quote->getId(),
            'merchant_currency' => $quote->getMerchantCurrency(),
            'shopper_currency' => $quote->getShopperCurrency(),
            'conversion_rate' => $quote->getConversionRate(),
        ]);
    }

    public function getConversionQuote(string $merchantCurrency, string $shopperCurrency): StructConversionQuote
    {
        return ObjectManager::getInstance()->get(ConversionQuoteUseCase::class)
            ->setMerchantCurrency($merchantCurrency)
            ->setShopperCurrency($shopperCurrency)
            ->get();
    }

    /**
     * Convert an amount using a client rate
     *
     * @param float $amount
     * @param mixed $rate
     * @param bool $reverse
     * @return float
     */
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
        $payment->setAdditionalInformation('intent_id', $intentId);
        $payment->setIsTransactionClosed(false);
    }

    /**
     * Convert a display amount to base currency and validate the original base total
     *
     * @param float $amount
     * @param float $rate
     * @param float $amountMax
     * @param float $baseAmountMax
     * @param string $currency
     * @param string $baseCurrency
     * @return float
     */
    public function getBaseAmount(
        float $amount,
        float $rate,
        float $amountMax,
        float $baseAmountMax,
        string $currency = '',
        string $baseCurrency = ''
    ): float {
        if ($currency === '' || $baseCurrency === '') {
            $baseAmount = $this->convertToDisplayCurrency($amount, $rate, true);
            return $this->isAmountEqual($amount, $amountMax) || $baseAmount - $baseAmountMax >= 0.0001
                ? $baseAmountMax : $baseAmount;
        }
        if ($this->isAmountEqual($amount, $amountMax, $currency)) {
            return $baseAmountMax;
        }
        $baseAmount = AmountHelper::formatAmount(
            (empty($rate) ? $amount : $amount / $rate),
            $baseCurrency
        );
        return $this->amountWithinOrderTotal($baseAmount, $baseAmountMax, $baseCurrency);
    }

    private function amountWithinOrderTotal(float $amount, float $total, string $currency): float
    {
        if (!is_finite($amount) || ($amount > $total && !$this->isAmountEqual($amount, $total, $currency))) {
            throw new LocalizedException(__('Payment amount exceeds the order total.'));
        }
        return $amount > $total ? $total : $amount;
    }

    /**
     * Convert an Airwallex card brand to Magento CC type
     *
     * @param string $type
     * @return string
     */
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

    /**
     * Compare two monetary amounts at the payment currency precision
     *
     * @param float $a
     * @param float $b
     * @param string $currency
     * @return bool
     */
    public function isAmountEqual(float $a, float $b, string $currency = ''): bool
    {
        return $currency === '' ? abs($a - $b) < 0.01 : AmountHelper::amountEquals($a, $b, $currency);
    }

    /**
     * Check whether a payment method code is a redirect APM constant
     *
     * @param mixed $string
     * @return bool
     */
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
        $items = method_exists($object, 'getAllVisibleItems')
            ? $object->getAllVisibleItems()
            : $object->getAllItems();
        foreach ($items as $item) {
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
    public function account(): StructAccount
    {
        return ObjectManager::getInstance()->get(Account::class)->send();
    }

    /**
     * @param Quote|Order $object
     *
     * @return array|null
     */
    public function getBillingAddress($object, ?string $email = null): ?array
    {
        $billingAddress = $object->getBillingAddress();
        if (empty($billingAddress)) return null;

        $data = [
            'first_name' => $billingAddress->getFirstname(),
            'last_name' => $billingAddress->getLastname(),
            'phone_number' => $billingAddress->getTelephone(),
            'address' => [
                'city' => $billingAddress->getCity(),
                'country_code' => $billingAddress->getCountryId(),
                'street' => $billingAddress->getStreet() ? implode(', ', $billingAddress->getStreet()) : '',
                'state' => $billingAddress->getRegion() ?: $billingAddress->getRegionCode(),
                'postcode' => $billingAddress->getPostcode(),
            ],
        ];

        if ($email !== null) {
            $data['email'] = $email ?: $billingAddress->getEmail();
        }

        return $data;
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

    /**
     * Cache key for a cancel request
     *
     * @param string $intentId
     * @return string
     */
    public function cancelCacheName(string $intentId): string
    {
        return $intentId . '_cancel';
    }

    /**
     * Check intent status if available to change order status
     *
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param Order|Quote $model
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    protected function checkIntent(StructPaymentIntent $paymentIntentFromApi, $model): void
    {
        $isOrder = $model instanceof Order;
        if ($isOrder && (!$model->getId() || $model->isCanceled())) {
            throw new InputException(__('Order is missing or canceled.'));
        }

        $currency = $this->getCurrencyCode($model);
        $orderIncrementId = $isOrder ? $model->getIncrementId() : $model->getReservedOrderId();
        $amount = $model->getGrandTotal();
        $intentAmount = $paymentIntentFromApi->getBaseCurrency() ? $paymentIntentFromApi->getBaseAmount() : $paymentIntentFromApi->getAmount();
        $intentCurrency = $paymentIntentFromApi->getBaseCurrency() ?: $paymentIntentFromApi->getCurrency();
        if (
            !($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured())
            || $paymentIntentFromApi->getMerchantOrderId() !== $orderIncrementId
            || !$this->intentMatchesAmountAndCurrency($paymentIntentFromApi, $model)
        ) {
            ObjectManager::getInstance()->get(RemoteLog::class)->error(
                'check intent failed',
                "Intent Status: {$paymentIntentFromApi->getStatus()}. Intent Order ID: {$paymentIntentFromApi->getMerchantOrderId()} - Order ID: $orderIncrementId - "
                    . "Intent Currency: $intentCurrency - Order Currency: $currency - "
                    . "Intent Amount: $intentAmount - Order Amount: $amount");
            $msg = 'Something went wrong while processing your request.';
            throw new Exception(__($msg));
        }
    }

    protected function intentMatchesAmountAndCurrency(StructPaymentIntent $intent, $model): bool
    {
        $currency = (string) $this->getCurrencyCode($model);
        $intentCurrency = $intent->getBaseCurrency() ?: $intent->getCurrency();
        $intentAmount = $intent->getBaseCurrency() ? $intent->getBaseAmount() : $intent->getAmount();
        return $intentCurrency === $currency
            && AmountHelper::amountEquals($intentAmount, $model->getGrandTotal(), $currency);
    }

    protected function assertIntentMatchesPayment(StructPaymentIntent $intent, $model): void
    {
        if ($this->intentMatchesAmountAndCurrency($intent, $model)) {
            return;
        }

        $currency = (string) $this->getCurrencyCode($model);
        $intentCurrency = $intent->getBaseCurrency() ?: $intent->getCurrency();
        $intentAmount = $intent->getBaseCurrency() ? $intent->getBaseAmount() : $intent->getAmount();
        ObjectManager::getInstance()->get(RemoteLog::class)->error(
            'payment amount mismatch',
            "Intent Currency: $intentCurrency - Order Currency: $currency - "
                . "Intent Amount: $intentAmount - Order Amount: {$model->getGrandTotal()}"
        );
        throw new LocalizedException(__('The payment amount or currency does not match the order total.'));
    }

    /**
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param Order $order
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function checkCardDetail(StructPaymentIntent $paymentIntentFromApi, Order $order): void
    {
        if (!ObjectManager::getInstance()->get(Configuration::class)->isPreVerificationEnabled()) return;
        if ($paymentIntentFromApi->getPaymentMethodType() === 'card') {
            $record = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getByIntentId($paymentIntentFromApi->getId());
            $detail = $record->getDetail();

            $isSame = true;
            $detailArray = $detail ? json_decode($detail, true) : [];
            if (!empty($detailArray['payment_method_ids'])) {
                $paymentMethodGet = ObjectManager::getInstance()->get(RetrievePaymentMethod::class);
                /** @var RetrievePaymentMethod $paymentMethodGet */
                $paymentMethodObject = $paymentMethodGet->setPaymentMethodId(end($detailArray['payment_method_ids']))->send();

                $intentCard = $paymentIntentFromApi->getLatestPaymentAttempt()['payment_method']['card'] ?? null;
                /** @var StructPaymentMethod $paymentMethodObject */
                $card = $paymentMethodObject->getCard() ?: null;
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

    /**
     * @param $payment
     * @param $agreement
     * @return void
     */
    public function setAgreementIds($payment, $agreement): void
    {
        $agreementIds = json_decode($agreement, true);
        if (empty($payment->getExtensionAttributes()->getAgreementIds()) && !empty($agreementIds)) {
            $paymentExtension = ObjectManager::getInstance()->get(PaymentExtension::class);
            $paymentExtension->setAgreementIds($agreementIds);
            $payment->setExtensionAttributes($paymentExtension);
        }
    }

    /**
     * Encode an error payload for frontend consumption
     *
     * @param mixed $message
     * @return string
     */
    protected function error($message)
    {
        return json_encode([
            'type' => 'error',
            'message' => $message
        ]);
    }

    /**
     * Append AVS/CVC verification details to the order comments
     *
     * @param Order $order
     * @param StructPaymentIntent $paymentIntentFromApi
     * @return void
     */
    protected function addAVSResultToOrder(Order $order, StructPaymentIntent $paymentIntentFromApi)
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
            $brand = $paymentIntentFromApi->getLatestPaymentAttempt()['payment_method']['card']['brand'] ?? '';
            if ($brand) $brand = ' Card Brand: ' . strtoupper($brand) . '.';
            $last4 = $paymentIntentFromApi->getLatestPaymentAttempt()['payment_method']['card']['last4'] ?? '';
            if ($last4) $last4 = ' Card Last Digits: ' . $last4 . '.';
            $avs_check = $paymentIntentFromApi->getLatestPaymentAttempt()['authentication_data']['avs_result'] ?? '';
            if ($avs_check) $avs_check = ' AVS Result: ' . $avs_check . '.';
            $cvc_check = $paymentIntentFromApi->getLatestPaymentAttempt()['authentication_data']['cvc_result'] ?? '';
            if ($cvc_check) $cvc_check = ' CVC Result: ' . $cvc_check . '.';
            $log .= $brand . $last4 . $avs_check . $cvc_check;
            if ($log === $src) return;
            $latestOrder = ObjectManager::getInstance()->get(OrderFactory::class)->create();
            ObjectManager::getInstance()->get(OrderResourceInterface::class)->load($latestOrder, $order->getId());
            $this->addComment($latestOrder, $log);
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
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

    /**
     * Format the order grand total for comments
     *
     * @param Order $order
     * @return string
     */
    public function totalPriceForComment(Order $order): string
    {
        return $this->priceForComment($order->getGrandTotal(), $order->getBaseGrandTotal(), $order);
    }

    /**
     * Format a price pair for order comments
     *
     * @param mixed $price
     * @param mixed $basePrice
     * @param Order $order
     * @return string
     */
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
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param int $orderId
     * @param Quote $quote
     * @param string $from
     * @return void
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws NoSuchEntityException
     */
    public function changeOrderStatus(StructPaymentIntent $paymentIntentFromApi, int $orderId, Quote $quote, string $from): void
    {
        ObjectManager::getInstance()->get(PaymentOperationLock::class)->execute(
            $orderId,
            function () use ($paymentIntentFromApi, $orderId, $quote, $from): void {
                $this->changeOrderStatusLocked($paymentIntentFromApi, $orderId, $quote, $from);
            },
            PaymentOperationLock::ORDER_COMPLETION_TIMEOUT
        );
    }

    private function changeOrderStatusLocked(StructPaymentIntent $paymentIntentFromApi, int $orderId, Quote $quote, string $from): void
    {
        $resource = ObjectManager::getInstance()->get(PaymentIntent::class);
        $connection = $resource->getConnection();
        $connection->beginTransaction();
        try {
            $query = $connection->select()
                ->from($resource->getMainTable())
                ->where('payment_intent_id = ?', $paymentIntentFromApi->getId())
                ->forUpdate(true);
            $connection->query($query);
            $this->logInfo("Start to change order status for order $orderId from $from");
            $order = $this->reloadLockedOrder($orderId);
            if (!$order->getId() || $order->isCanceled()) {
                throw new InputException(__('Order is missing or canceled.'));
            }
            $this->checkIntent($paymentIntentFromApi, $order);
            $this->applyIntentToOrder($order, $paymentIntentFromApi, true);
            $this->deactivateQuote($quote);
            $this->logInfo("Finish to change order status for order $orderId from $from");
            $connection->commit();
            $this->setCheckoutSuccess($quote->getId(), $order);
            PaymentFlowLog::orderStatusUpdated($order, (string) $paymentIntentFromApi->getId(), $from);
        }   catch (Exception $e) {
            $connection->rollBack();
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw $e;
        }
    }

    protected function applyIntentToOrder(
        Order $order,
        StructPaymentIntent $intent,
        bool $clearCompanyOrder = false
    ): void {
        $payment = $order->getPayment();
        if ($this->isOrderFullyPaid($order)
            || ($payment && $payment->getAmountAuthorized() > 0 && $intent->isAuthorized())
        ) {
            return;
        }
        if ($payment && ($payment->getAmountAuthorized() > 0 || $order->getTotalPaid() > 0
                || $order->getInvoiceCollection()->getSize() > 0)
            && $intent->isCaptured()
        ) {
            $this->invoiceAuthorizedOrder($order, $intent);
            return;
        }
        $this->setTransactionId($payment, $intent->getId());
        ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($intent);
        if ($clearCompanyOrder && $this->isMiniPluginExists()) {
            $companyOrder = ObjectManager::getInstance()->get('\Magento\Company\Api\Data\CompanyOrderInterfaceFactory')->create();
            $companyResource = ObjectManager::getInstance()->get('\Magento\Company\Model\ResourceModel\Order');
            $companyResource->load($companyOrder, $order->getId(), 'order_id');
            if ($companyOrder && $companyOrder->getId()) {
                $companyResource->delete($companyOrder);
            }
        }
        $order->place();
        ObjectManager::getInstance()->get(OrderRepository::class)->save($order);
        if ($intent->isCaptured()) {
            $this->invoiceAuthorizedOrder($order, $intent);
        }
        $this->addAVSResultToOrder($order, $intent);
        try {
            $this->checkCardDetail($intent, $order);
        } catch (Exception $e) {
            $this->logError('checkCardDetail failed: ' . $e->getMessage());
        }
    }

    protected function invoiceAuthorizedOrder(Order $order, StructPaymentIntent $paymentIntentFromApi): bool
    {
        if (!$this->isCaptureConfirmed($paymentIntentFromApi)) {
            return false;
        }
        $order = $this->reloadLockedOrder((int)$order->getId());
        if (!$order->getId() || $order->isCanceled() || !$order->getPayment()
            || $order->getTotalPaid() > 0 || $order->getBaseTotalPaid() > 0
        ) {
            return false;
        }
        $targets = $this->capturedPaymentTargets($order, $paymentIntentFromApi);
        if ($targets === null) {
            return false;
        }
        $invoice = null;
        foreach ($order->getInvoiceCollection() as $existing) {
            if ($existing->getState() == Invoice::STATE_CANCELED) {
                continue;
            }
            if ($invoice || $existing->getState() == Invoice::STATE_PAID
                || !AmountHelper::amountEquals($existing->getGrandTotal(), $targets['order'], $order->getOrderCurrencyCode())
                || !AmountHelper::amountEquals($existing->getBaseGrandTotal(), $targets['base'], $order->getBaseCurrencyCode())
            ) {
                throw new LocalizedException(__('The existing invoice does not match the confirmed Airwallex capture.'));
            }
            $invoice = $existing;
        }
        $existingInvoice = $invoice !== null;
        if (!$invoice) {
            $invoice = ObjectManager::getInstance()->get(InvoiceService::class)->prepareInvoice($order);
            $invoice->setGrandTotal($targets['order']);
            $invoice->setBaseGrandTotal($targets['base']);
        }
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $this->addComment($order, 'Captured amount of '
            . $this->priceForComment($targets['order'], $targets['base'], $order)
            . ' through Airwallex. Transaction ID: "' . $paymentIntentFromApi->getId() . '".');
        $order->getPayment()->setIsTransactionPending(false);
        $invoice->setTransactionId($paymentIntentFromApi->getId());
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->setCanVoidFlag(false);
        if ($existingInvoice) {
            $invoice->setIsPaid(true);
            $invoice->pay();
        } else {
            $invoice->register();
        }
        ObjectManager::getInstance()->get(TransactionFactory::class)->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
        return true;
    }

    protected function reloadLockedOrder(int $orderId): Order
    {
        $connection = ObjectManager::getInstance()->get(PaymentIntent::class)->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($connection->getTableName('sales_order'), [
                    'entity_id',
                    'state',
                    'status',
                    'total_paid',
                    'base_total_paid',
                    'grand_total',
                    'base_grand_total',
                ])
                ->where('entity_id = ?', $orderId)
                ->forUpdate(true)
        );
        $order = $this->getFreshOrder($orderId);
        if (is_array($row)) {
            $order->setState((string)$row['state']);
            $order->setStatus((string)$row['status']);
            $order->setTotalPaid($row['total_paid']);
            $order->setBaseTotalPaid($row['base_total_paid']);
            $order->setGrandTotal($row['grand_total']);
            $order->setBaseGrandTotal($row['base_grand_total']);
        }
        return $order;
    }

    protected function capturedPaymentTargets(Order $order, StructPaymentIntent $intent): ?array
    {
        $capturedAmount = (float)$intent->getCapturedAmount();
        $intentAmount = (float)$intent->getAmount();
        $currency = (string)$intent->getCurrency();
        if ($capturedAmount <= 0 || $intentAmount <= 0) {
            return null;
        }
        if (AmountHelper::formatAmount($capturedAmount, $currency) > AmountHelper::formatAmount($intentAmount, $currency)) {
            throw new LocalizedException(__('Invalid captured amount.'));
        }

        if ($currency === $order->getOrderCurrencyCode()) {
            $targetOrderPaid = round($capturedAmount, 4);
            $targetBasePaid = $this->convertToDisplayCurrency($capturedAmount, $order->getBaseToOrderRate(), true);
        } elseif ($currency === $order->getBaseCurrencyCode()) {
            $targetBasePaid = round($capturedAmount, 4);
            $targetOrderPaid = $this->convertToDisplayCurrency($targetBasePaid, $order->getBaseToOrderRate(), false);
        } else {
            $ratio = $capturedAmount / $intentAmount;
            $targetBasePaid = round($ratio * (float)$order->getBaseGrandTotal(), 4);
            $targetOrderPaid = round($ratio * (float)$order->getGrandTotal(), 4);
        }
        // Only an exact match at Magento's storage scale may settle the full order.
        if (round($capturedAmount, 4) === round($intentAmount, 4)) {
            $targetOrderPaid = (float)$order->getGrandTotal();
            $targetBasePaid = (float)$order->getBaseGrandTotal();
        }
        return [
            'order' => $this->amountWithinOrderTotal(
                $targetOrderPaid, (float)$order->getGrandTotal(), (string)$order->getOrderCurrencyCode()
            ),
            'base' => $this->amountWithinOrderTotal(
                $targetBasePaid, (float)$order->getBaseGrandTotal(), (string)$order->getBaseCurrencyCode()
            ),
        ];
    }

    protected function isCaptureConfirmed(StructPaymentIntent $paymentIntent): bool
    {
        $captured = (float)$paymentIntent->getCapturedAmount();
        return $paymentIntent->getStatus() === StructPaymentIntent::STATUS_SUCCEEDED
            && is_finite($captured) && $captured > 0;
    }

    protected function isFullCaptureConfirmed(StructPaymentIntent $paymentIntent): bool
    {
        return $this->isCaptureConfirmed($paymentIntent)
            && AmountHelper::amountEquals($paymentIntent->getCapturedAmount(), $paymentIntent->getAmount(), $paymentIntent->getCurrency());
    }

    protected function isOrderFullyPaid(Order $order): bool
    {
        return $order->getTotalPaid() > 0
            && (round((float) $order->getTotalPaid(), 4) === round((float) $order->getGrandTotal(), 4)
                || (float) $order->getTotalPaid() >= (float) $order->getGrandTotal());
    }

    /**
     * Complete an order only when Airwallex reports an authoritative paid status.
     *
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param mixed $paymentIntentRecord
     * @param Quote $quote
     * @param string $from
     * @return bool
     * @throws Exception
     */
    public function completePaymentIfSuccessful(
        StructPaymentIntent $paymentIntentFromApi,
        $paymentIntentRecord,
        Quote $quote,
        string $from
    ): bool {
        if (!$paymentIntentFromApi->isAuthorized() && !$paymentIntentFromApi->isCaptured()) {
            return false;
        }

        // Klarna (and the other full-page redirects) unload the checkout page, so
        // the browser poll never logs success. Record it as soon as Airwallex
        // says the intent is paid. Placing the Magento order can still throw
        // afterwards (currency switcher, amount check) and must not drop the event.
        // The intent-status poll uses this same method; the cache inside
        // checkoutSucceeded keeps a second call from logging again.
        $intentId = is_object($paymentIntentRecord) && method_exists($paymentIntentRecord, 'getIntentId')
            ? (string) $paymentIntentRecord->getIntentId()
            : '';
        PaymentFlowLog::checkoutSucceeded($quote, $intentId);

        if ($this->isOrderBeforePayment()) {
            $this->changeOrderStatus($paymentIntentFromApi, $paymentIntentRecord->getOrderId(), $quote, $from);
        } else {
            $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, $from);
        }

        return true;
    }

    /**
     * Deactivate a quote after payment is complete
     *
     * @param Quote $quote
     * @return void
     */
    public function deactivateQuote(Quote $quote)
    {
        if (!empty($quote) && $quote->getIsActive()) {
            $quote->setIsActive(false);
            ObjectManager::getInstance()->get(QuoteRepository::class)->save($quote);
        }
    }

    /**
     * Load a fresh order instance by id
     *
     * @param int $orderId
     * @return Order
     */
    public function getFreshOrder(int $orderId)
    {
        $order = ObjectManager::getInstance()->get(OrderFactory::class)->create();
        ObjectManager::getInstance()->get(OrderResourceInterface::class)->load($order, $orderId);
        return $order;
    }


    /**
     * @param $paymentMethod
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param Quote $quote
     * @param string $from
     * @param null $billingAddress
     * @throws GuzzleException
     * @throws Exception
     */
    public function placeOrder($paymentMethod, StructPaymentIntent $paymentIntentFromApi, Quote $quote, string $from = '', $billingAddress = null)
    {
        $order = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getOrder($paymentIntentFromApi->getId());
        $operation = function () use ($paymentMethod, $paymentIntentFromApi, $quote, $from, $billingAddress) {
            return $this->placeOrderLocked($paymentMethod, $paymentIntentFromApi, $quote, $from, $billingAddress);
        };
        if ($order && $order->getId()) {
            return ObjectManager::getInstance()->get(PaymentOperationLock::class)
                ->execute((int)$order->getId(), $operation, PaymentOperationLock::ORDER_COMPLETION_TIMEOUT);
        }
        return $operation();
    }

    private function placeOrderLocked($paymentMethod, StructPaymentIntent $paymentIntentFromApi, Quote $quote, string $from, $billingAddress)
    {
        $quoteId = $quote->getId();

        $resource = ObjectManager::getInstance()->get(PaymentIntent::class);
        $connection = $resource->getConnection();
        $connection->beginTransaction();
        try {
            $query = $connection->select()
                ->from($resource->getMainTable())
                ->where('payment_intent_id = ?', $paymentIntentFromApi->getId())
                ->forUpdate(true);
            $connection->query($query);
            $this->logInfo("Start to place order for quote $quoteId from $from");
            $repository = ObjectManager::getInstance()->get(PaymentIntentRepository::class);
            $paymentIntentRecord = $repository->getByIntentId($paymentIntentFromApi->getId());
            if ((int) $paymentIntentRecord->getQuoteId() !== (int) $quoteId
                || (int) $paymentIntentRecord->getStoreId() !== (int) $quote->getStoreId()
            ) {
                throw new InputException(__('Payment intent does not match the quote.'));
            }
            $order = $repository->getOrder($paymentIntentFromApi->getId());
            $this->checkIntent($paymentIntentFromApi, $order ?: $quote);
            $detail = json_decode($paymentIntentRecord->getDetail(), true);
            $payment = $paymentMethod ?: $quote->getPayment();
            if ($payment->getMethod() === Vault::CODE) {
                $payment->setMethod(CardMethod::CODE);
            }

            $this->setAgreementIds($payment, $detail['agreement']);
            if (empty($order) || empty($order->getId())) {
                /** @var StructPaymentIntent $paymentIntent */
                $paymentIntentFromApi = ObjectManager::getInstance()->get(RetrievePaymentIntent::class)->setPaymentIntentId($paymentIntentFromApi->getId())->send();
                ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($paymentIntentFromApi);
                $orderId = $this->placeOrderFromRecalculatedQuote($payment, $paymentIntentFromApi, $quote, $billingAddress, is_array($detail) ? $detail : []);

                ObjectManager::getInstance()->get(PaymentIntentRepository::class)->updateOrderId($paymentIntentRecord, $orderId);
                $order = $this->getFreshOrder($orderId);
                $this->checkIntent($paymentIntentFromApi, $order);
                if ($paymentIntentFromApi->isCaptured() && !$this->isFullCaptureConfirmed($paymentIntentFromApi)) {
                    $this->invoiceAuthorizedOrder($order, $paymentIntentFromApi);
                }
                $this->addAVSResultToOrder($order, $paymentIntentFromApi);
                try {
                    $this->checkCardDetail($paymentIntentFromApi, $order);
                } catch (Exception $e) {
                    $this->logError('checkCardDetail failed: ' . $e->getMessage());
                }
            } else if (in_array($order->getStatus(), [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW, 'pending'])) {
                ObjectManager::getInstance()->get(PaymentOperationLock::class)
                    ->assertHeld((int)$order->getId());
                /** @var StructPaymentIntent $paymentIntent */
                $paymentIntentFromApi = ObjectManager::getInstance()->get(RetrievePaymentIntent::class)->setPaymentIntentId($paymentIntentFromApi->getId())->send();
                $order = $this->reloadLockedOrder((int)$order->getId());
                if ($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured()) {
                    $this->checkIntent($paymentIntentFromApi, $order);
                    if (!$paymentIntentRecord->getOrderId()) {
                        ObjectManager::getInstance()->get(PaymentIntentRepository::class)->updateOrderId($paymentIntentRecord, (int) $order->getId());
                    }
                    $this->applyIntentToOrder($order, $paymentIntentFromApi);
                } else {
                    $this->logInfo("Can not place order for quote $quoteId from $from");
                    throw new Exception("invalid payment intent");
                }
            }

            $this->deactivateQuote($quote);

            $this->logInfo("Successfully placed order for quote ID: $quoteId from $from.");
            $connection->commit();
            $this->setCheckoutSuccess($quoteId, $order);
            if (!empty($order) && $order->getId()) {
                PaymentFlowLog::orderStatusUpdated($order, (string) $paymentIntentFromApi->getId(), $from);
            }
        }   catch (Exception $e) {
            $connection->rollBack();
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw $e;
        }
    }

    private function placeOrderFromRecalculatedQuote($payment, StructPaymentIntent $intent, Quote $quote, $billingAddress, array $detail): int
    {
        $quoteId = (int) $quote->getId();
        $isCustomer = !empty($detail['uid']);
        $cartId = '';
        if ($isCustomer) {
            ObjectManager::getInstance()->get(PaymentInformationManagementInterface::class)
                ->savePaymentInformation($quoteId, $payment, $billingAddress);
        } else {
            $cartId = ObjectManager::getInstance()->get(QuoteIdToMaskedQuoteIdInterface::class)->execute($quoteId);
            $customer = $intent->getCustomer();
            ObjectManager::getInstance()->get(GuestPaymentInformationManagementInterface::class)
                ->savePaymentInformation(
                    $cartId,
                    is_array($customer) ? (string) ($customer['email'] ?? '') : '',
                    $payment,
                    $billingAddress
                );
        }

        $recalculated = ObjectManager::getInstance()->get(QuoteRepository::class)->get($quoteId);
        $recalculated->setTotalsCollectedFlag(false);
        $recalculated->collectTotals();
        $this->checkIntent($intent, $recalculated);

        if ($isCustomer) {
            return (int) ObjectManager::getInstance()->get(CartManagementInterface::class)->placeOrder($quoteId);
        }

        return (int) ObjectManager::getInstance()->get(GuestCartManagementInterface::class)->placeOrder($cartId);
    }

    /**
     * Persist Magento checkout success session values
     *
     * @param mixed $quoteId
     * @param Order $order
     * @return void
     */
    public function setCheckoutSuccess($quoteId, $order)
    {
        if (!$order || !$order->getId()) {
            return;
        }
        if ($this->entityCustomerId($order) > 0 && !$this->validateOrderOwnership($order)) {
            return;
        }
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

    /**
     * Build Magento referrer data type for an Airwallex request
     *
     * @param mixed $paymentMethod
     * @param string $from
     * @return string
     */
    public function getReferrerDataType($paymentMethod, $from = '')
    {
        $code = $this->getPaymentMethodCode($paymentMethod->getMethod());

        if ($code === 'express' && in_array($from, ['googlepay', 'applepay'], true)) {
            return "magento_{$from}";
        }

        if ($code === 'card') {
            return "magento_credit_card";
        }

        if ($code !== 'express') {
            return "magento_{$code}";
        }

        return 'magento';
    }

    /**
     * Check whether the mini-feature plugin is present
     *
     * @return bool
     */
    public function isMiniPluginExists(): bool
    {
        return file_exists('../app/code/airwallex/paymentacceptance-minifeature-magento-admin-card/Model/CompanyConsents.php');
    }

    /**
     * Get currency code from a quote or order
     *
     * @param Quote|Order $model
     * @return string
     */
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
        return ObjectManager::getInstance()->get(CurrencySwitcherAvailableCurrencies::class)->get();
    }

    /**
     * Check whether Magento places the order before capturing payment
     *
     * @return bool
     */
    public function isOrderBeforePayment(): bool
    {
        return ObjectManager::getInstance()->get(Configuration::class)->isOrderBeforePayment();
    }

    /**
     * Strip Magento payment method prefix from a method code
     *
     * @param string $code
     * @return string
     */
    public function trimPaymentMethodCode(string $code): string
    {
        $code = str_replace(AbstractMethod::PAYMENT_PREFIX, '', $code);
        return str_replace('airwallex_cc_', '', $code);
    }

    /**
     * Write an error log entry
     *
     * @param string $message
     * @return void
     */
    public function logError(string $message)
    {
        ObjectManager::getInstance()->get(LoggerInterface::class)->error($message);
    }

    /**
     * Write a debug log entry
     *
     * @param string $message
     * @return void
     */
    public function logInfo(string $message)
    {
        ObjectManager::getInstance()->get(LoggerInterface::class)->debug($message);
    }

    /**
     * Resolve a numeric quote id from a masked or numeric identifier
     *
     * @param mixed $quoteId
     * @return int
     */
    public function resolveQuoteId($quoteId): int
    {
        if (is_numeric($quoteId)) {
            return (int)$quoteId;
        }

        return ObjectManager::getInstance()->get(MaskedQuoteIdToQuoteIdInterface::class)->execute($quoteId);
    }

    /**
     * Validate that a quote belongs to the current customer or checkout session
     *
     * @param mixed $quote
     * @param mixed $customerSession
     * @param mixed $checkoutSession
     * @return bool
     */
    public function validateQuoteOwnership($quote, $customerSession = null, $checkoutSession = null): bool
    {
        $customerSession = $customerSession ?: ObjectManager::getInstance()->get(CustomerSession::class);

        if ($customerSession->isLoggedIn()) {
            return $this->validateCustomerOwnership($quote, $customerSession->getCustomerId(), 'Quote');
        }

        $quoteCustomerId = $quote->getCustomerId()
            ?: (method_exists($quote, 'getCustomer') && $quote->getCustomer() ? $quote->getCustomer()->getId() : null);
        if ($quoteCustomerId) {
            $this->logError("Guest user attempted to access registered customer quote {$quote->getId()}");
            return false;
        }

        $checkoutSession = $checkoutSession ?: ObjectManager::getInstance()->get(CheckoutSession::class);
        $sessionQuoteId = $checkoutSession->getQuoteId();
        if ($quote->getId() != $sessionQuoteId) {
            $this->logError("Quote {$quote->getId()} is not in current session. Session quote: {$sessionQuoteId}");
            return false;
        }

        return true;
    }

    /**
     * Verify that a payment intent belongs to the active checkout quote and caller.
     *
     * @param mixed $paymentIntent
     * @param mixed $quote
     * @param string $requestCartId
     * @param mixed $customerSession
     * @param mixed $checkoutSession
     * @return bool
     */
    public function isIntentOwnedByQuote(
        $paymentIntent,
        $quote,
        string $requestCartId = '',
        $customerSession = null,
        $checkoutSession = null
    ): bool {
        if (!$paymentIntent || !is_object($paymentIntent) || !method_exists($paymentIntent, 'getQuoteId')
            || !$quote || !is_object($quote) || !method_exists($quote, 'getId')
        ) {
            return false;
        }

        $quoteId = (int) $quote->getId();
        if ($quoteId <= 0 || (int) $paymentIntent->getQuoteId() !== $quoteId) {
            return false;
        }

        if (!$this->validateQuoteOwnership($quote, $customerSession, $checkoutSession)) {
            return false;
        }

        $requestCartId = trim($requestCartId);
        if ($requestCartId !== '') {
            try {
                if ($this->resolveQuoteId($requestCartId) !== $quoteId) {
                    return false;
                }
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authorize an intent lookup when the checkout session's quote was already
     * deactivated (e.g. by a concurrent webhook).
     *
     * @param mixed $paymentIntent
     * @param string $requestCartId
     * @param mixed $intentQuote
     * @param mixed $order
     * @param mixed $customerSession
     * @param mixed $checkoutSession
     * @return bool
     */
    public function isDeactivatedIntentQuoteAuthorized(
        $paymentIntent,
        string $requestCartId = '',
        $intentQuote = null,
        $order = null,
        $customerSession = null,
        $checkoutSession = null
    ): bool {
        if (!$paymentIntent || !is_object($paymentIntent) || !method_exists($paymentIntent, 'getQuoteId')) {
            return false;
        }

        $intentQuoteId = (int) $paymentIntent->getQuoteId();
        if ($intentQuoteId <= 0) {
            return false;
        }

        try {
            if ($intentQuote === null) {
                $intentQuote = ObjectManager::getInstance()->get(QuoteRepository::class)->get($intentQuoteId);
            }
        } catch (Exception $e) {
            return false;
        }

        if (!$intentQuote || (int) $intentQuote->getId() !== $intentQuoteId) {
            return false;
        }
        if ($intentQuote && $this->validateQuoteOwnership($intentQuote, $customerSession, $checkoutSession)) {
            return true;
        }

        if ($order === null && method_exists($paymentIntent, 'getOrderId') && (int) $paymentIntent->getOrderId() > 0) {
            try {
                $order = $this->getFreshOrder((int) $paymentIntent->getOrderId());
            } catch (Exception $e) {
                $order = null;
            }
        }

        if ($order && $order->getId() && (int) $order->getQuoteId() === $intentQuoteId
            && $this->validateOrderOwnership($order, $customerSession, $checkoutSession)
        ) {
            return true;
        }

        $quoteCustomerId = (int) $intentQuote->getCustomerId();
        $requestCartId = trim($requestCartId);
        if ($quoteCustomerId === 0 && $requestCartId !== '' && !is_numeric($requestCartId)) {
            try {
                return $this->resolveQuoteId($requestCartId) === $intentQuoteId;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Validate that an order belongs to the current customer or checkout session
     *
     * @param mixed $order
     * @param mixed $customerSession
     * @param mixed $checkoutSession
     * @return bool
     */
    public function validateOrderOwnership($order, $customerSession = null, $checkoutSession = null): bool
    {
        $customerSession = $customerSession ?: ObjectManager::getInstance()->get(CustomerSession::class);

        if ($customerSession->isLoggedIn()) {
            return $this->validateCustomerOwnership($order, $customerSession->getCustomerId(), 'Order');
        }

        if ($order->getCustomerId()) {
            $this->logError("Guest user attempted to access registered customer order {$order->getId()}");
            return false;
        }

        $checkoutSession = $checkoutSession ?: ObjectManager::getInstance()->get(CheckoutSession::class);
        $sessionOrderId = (int) $checkoutSession->getLastOrderId();
        $sessionQuoteId = (int) $checkoutSession->getLastQuoteId();
        if ((int) $order->getId() !== $sessionOrderId
            || (int) $order->getQuoteId() !== $sessionQuoteId
        ) {
            $this->logError(
                "Guest order {$order->getId()} is not in current checkout session. "
                . "Session order: {$sessionOrderId}; session quote: {$sessionQuoteId}"
            );
            return false;
        }

        return true;
    }

    /**
     * Get the signed return-state service
     *
     * @return ReturnState
     */
    public function returnState(): ReturnState
    {
        return ObjectManager::getInstance()->get(ReturnState::class);
    }

    /**
     * Generate a signed return-state token, or an empty string on failure
     *
     * @param string $scope
     * @param int $id
     * @param int $ttl
     * @return string
     */
    public function generateReturnState(string $scope, int $id, int $ttl = ReturnState::RETURN_TTL): string
    {
        try {
            if (!$this->returnState()->isConfigured()) {
                $this->logError('Cannot generate signed return state: crypt/key is not configured.');
                return '';
            }

            return $this->returnState()->generate($scope, $id, $ttl);
        } catch (Exception $e) {
            $this->logError('Unable to generate signed return state: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Validate a signed return-state token for a scope and entity id
     *
     * @param string $state
     * @param string $scope
     * @param int $id
     * @param ReturnState|null $returnState
     * @return bool
     */
    public function validateReturnState(string $state, string $scope, int $id, $returnState = null): bool
    {
        if ($id <= 0) {
            return false;
        }

        $returnState = $returnState ?: $this->returnState();
        if (!$returnState->isConfigured()) {
            throw new SigningKeyMissingException();
        }

        if ($state === '') {
            return false;
        }

        return $returnState->validate($state, $scope, $id);
    }

    /**
     * Allow order access via session ownership or a valid signed state
     *
     * @param mixed $order
     * @param string $state
     * @param ReturnState|null $returnState
     * @param mixed $customerSession
     * @return bool
     */
    public function validateOrderAccess($order, string $state, $returnState = null, $customerSession = null): bool
    {
        if ($this->validateOrderOwnership($order, $customerSession)) {
            return true;
        }

        if (!$order || !$order->getId()
            || $this->entityCustomerId($order) > 0
            || $this->isLoggedInCustomer($customerSession)
        ) {
            return false;
        }

        return $this->validateReturnState(
            $state,
            ReturnState::SCOPE_ORDER,
            (int) $order->getId(),
            $returnState
        );
    }

    /**
     * Allow quote access via session ownership or a valid signed state
     *
     * @param mixed $quote
     * @param string $state
     * @param ReturnState|null $returnState
     * @param mixed $customerSession
     * @return bool
     */
    public function validateQuoteAccess($quote, string $state, $returnState = null, $customerSession = null): bool
    {
        if ($this->validateQuoteOwnership($quote, $customerSession)) {
            return true;
        }

        if (!$quote || !$quote->getId()
            || $this->entityCustomerId($quote) > 0
            || $this->isLoggedInCustomer($customerSession)
        ) {
            return false;
        }

        return $this->validateReturnState(
            $state,
            ReturnState::SCOPE_QUOTE,
            (int) $quote->getId(),
            $returnState
        );
    }

    /**
     * Allow intent access via a signed state for the related order or quote
     *
     * @param mixed $intentRecord
     * @param string $state
     * @param ReturnState|null $returnState
     * @param mixed $customerSession
     * @return bool
     */
    public function validateIntentStateAccess(
        $intentRecord,
        string $state,
        $returnState = null,
        $customerSession = null
    ): bool {
        if (!$intentRecord
            || $this->intentCustomerId($intentRecord) > 0
            || $this->isLoggedInCustomer($customerSession)
        ) {
            return false;
        }

        if ((int) $intentRecord->getOrderId() > 0
            && $this->validateReturnState(
                $state,
                ReturnState::SCOPE_ORDER,
                (int) $intentRecord->getOrderId(),
                $returnState
            )
        ) {
            return true;
        }

        if ((int) $intentRecord->getQuoteId() > 0
            && $this->validateReturnState(
                $state,
                ReturnState::SCOPE_QUOTE,
                (int) $intentRecord->getQuoteId(),
                $returnState
            )
        ) {
            return true;
        }

        return false;
    }

    public function consumeReturnStateToken(string $state, $returnState = null): void
    {
        if ($state === '') {
            return;
        }
        try {
            $returnState = $returnState ?: $this->returnState();
            if ($returnState->isConfigured()) {
                $returnState->consumeToken($state);
            }
        } catch (Exception $e) {
            $this->logError('Unable to consume signed return state: ' . $e->getMessage());
        }
    }

    public function consumeReturnState(int $quoteId, int $orderId = 0, string $state = '', $returnState = null): void
    {
        try {
            $returnState = $returnState ?: $this->returnState();
            if (!$returnState->isConfigured()) {
                return;
            }
            if ($state !== '') {
                $returnState->consumeToken($state);
            }
            if ($quoteId > 0) {
                $returnState->consumeEntity(ReturnState::SCOPE_QUOTE, $quoteId);
            }
            if ($orderId > 0) {
                $returnState->consumeEntity(ReturnState::SCOPE_ORDER, $orderId);
            }
        } catch (Exception $e) {
            $this->logError('Unable to consume signed return state: ' . $e->getMessage());
        }
    }

    protected function entityCustomerId($entity): int
    {
        if (!is_object($entity)) {
            return 0;
        }
        if (method_exists($entity, 'getCustomerId') && $entity->getCustomerId()) {
            return (int) $entity->getCustomerId();
        }
        if (method_exists($entity, 'getCustomer') && $entity->getCustomer()
            && method_exists($entity->getCustomer(), 'getId')
        ) {
            return (int) $entity->getCustomer()->getId();
        }
        return 0;
    }

    protected function intentCustomerId($intentRecord): int
    {
        if (is_object($intentRecord) && method_exists($intentRecord, 'getDetail')) {
            $detail = json_decode((string) $intentRecord->getDetail(), true);
            if (is_array($detail) && !empty($detail['uid'])) {
                return (int) $detail['uid'];
            }
        }
        return 0;
    }

    protected function isLoggedInCustomer($customerSession = null): bool
    {
        if ($customerSession === null) {
            try {
                $customerSession = ObjectManager::getInstance()->get(CustomerSession::class);
            } catch (\RuntimeException $e) {
                return false;
            }
        }
        return is_object($customerSession) && method_exists($customerSession, 'isLoggedIn')
            && (bool) $customerSession->isLoggedIn();
    }

    /**
     * Validate that an entity belongs to the given customer
     *
     * @param mixed $entity
     * @param mixed $customerId
     * @param string $entityType
     * @return bool
     */
    private function validateCustomerOwnership($entity, $customerId, $entityType): bool
    {
        if ($entity->getCustomerId() != $customerId) {
            $this->logError("{$entityType} {$entity->getId()} does not belong to customer {$customerId}");
            return false;
        }
        return true;
    }

    /**
     * Build plugin metadata for Airwallex requests
     *
     * @return array
     */
    protected function getMetadata(): array
    {
        $productMetadata = ObjectManager::getInstance()->get(ProductMetadataInterface::class);
        $configuration = ObjectManager::getInstance()->get(Configuration::class);
        $moduleList = ObjectManager::getInstance()->get(ModuleListInterface::class);
        $metadata = [
            'php_version' => phpversion(),
            'magento_version' => $productMetadata->getVersion(),
            'plugin_version' => $moduleList->getOne(Configuration::MODULE_NAME)['setup_version'],
            'is_card_active' => $configuration->isCardActive() ?? false,
            'is_card_auto_capture' => $configuration->isAutoCapture('card') ?? false,
            'is_card_vault_active' => $configuration->isCardVaultActive() ?? false,
            'is_express_active' => $configuration->isExpressActive() ?? false,
            'is_express_auto_capture' => $configuration->isAutoCapture('express') ?? false,
            'express_display_area' => $configuration->expressDisplayArea() ?? '',
            'is_request_logger_enable' => $configuration->isRequestLoggerEnable() ?? false,
            'express_checkout' => $configuration->getCheckout() ?? '',
            'is_order_before_payment' => $configuration->isOrderBeforePayment(),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
        ];

        foreach (array_keys(RedirectMethod::displayNames()) as $paymentMethod) {
            $paymentMethod = str_replace(AbstractMethod::PAYMENT_PREFIX, '', $paymentMethod);
            $metadata['is_' . $paymentMethod . '_active'] = $configuration->isMethodActive($paymentMethod);
        }

        return $metadata;
    }
}
