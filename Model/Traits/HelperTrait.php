<?php

namespace Airwallex\Payments\Model\Traits;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\CurrencySwitcher;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\IntentHelper;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\Config\CurrencySwitcherAvailableCurrencies;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Airwallex\Payments\Model\Methods\RedirectMethod;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethod\Get as RetrievePaymentMethod;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethod as StructPaymentMethod;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Airwallex\Payments\Model\Methods\CardMethod;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\PaymentExtension;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Account;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Quote as StructQuote;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Account as StructAccount;

trait HelperTrait
{
    /**
     * Currency switcher
     *
     * @param string $paymentCurrency
     * @param string $targetCurrency
     * @param string $amount
     * @return string
     */
    public function currencySwitcher(string $paymentCurrency, string $targetCurrency, string $amount): string
    {
        $switcher = $this->getCurrencySwitcher($paymentCurrency, $targetCurrency, $amount);
        return json_encode([
            'id' => $switcher->getId(),
            'payment_currency' => $switcher->getPaymentCurrency(),
            'target_currency' => $switcher->getTargetCurrency(),
            'payment_amount' => $switcher->getPaymentAmount(),
            'target_amount' => $switcher->getTargetAmount(),
            'client_rate' => $switcher->getClientRate(),
        ]);
    }

    public function getCurrencySwitcher(string $paymentCurrency, string $targetCurrency, string $amount): StructQuote
    {
        return ObjectManager::getInstance()->get(CurrencySwitcher::class)->setPaymentCurrency($paymentCurrency)
            ->setTargetCurrency($targetCurrency)
            ->setPaymentAmount($amount)
            ->get();
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
    public function account(): StructAccount
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
     * @param StructPaymentIntent $paymentIntentFromApi
     * @param Order|Quote $model
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    protected function checkIntent(StructPaymentIntent $paymentIntentFromApi, $model): void
    {
        $isOrder = $model instanceof Order;

        $currency = $this->getCurrencyCode($model);
        $orderIncrementId = $isOrder ? $model->getIncrementId() : $model->getReservedOrderId();
        $amount = $model->getGrandTotal();
        $intentAmount = $paymentIntentFromApi->getBaseAmount() ?: $paymentIntentFromApi->getAmount();
        $intentCurrency = $paymentIntentFromApi->getBaseCurrency() ?: $paymentIntentFromApi->getCurrency();
        if (
            !($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured())
            || $intentCurrency !== $currency
            || $paymentIntentFromApi->getMerchantOrderId() !== $orderIncrementId
            || !$this->isAmountEqual($intentAmount, $amount)
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

    protected function error($message)
    {
        return json_encode([
            'type' => 'error',
            'message' => $message
        ]);
    }

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
            $order = $this->getFreshOrder($orderId);
            /** @var Payment $payment */
            $payment = $order->getPayment();
            if (($payment && $payment->getAmountAuthorized() > 0 && $paymentIntentFromApi->isAuthorized()) || $order->getTotalPaid() > 0) {
                $this->deactivateQuote($quote);
                $connection->commit();
                $this->setCheckoutSuccess($quote->getId(), $order);
                return;
            }
            $this->checkIntent($paymentIntentFromApi, $order);
            $this->setTransactionId($order->getPayment(), $paymentIntentFromApi->getId());
            ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($paymentIntentFromApi);
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

            $this->addAVSResultToOrder($order, $paymentIntentFromApi);

            $this->deactivateQuote($quote);

            try {
                $this->checkCardDetail($paymentIntentFromApi, $order);
            } catch (Exception $e) {
                $this->logError('checkCardDetail failed: ' . $e->getMessage());
            }
            $this->logInfo("Finish to change order status for order $orderId from $from");
            $connection->commit();
            $this->setCheckoutSuccess($quote->getId(), $order);
        }   catch (Exception $e) {
            $connection->rollBack();
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw $e;
        }
    }

    public function deactivateQuote(Quote $quote)
    {
        if (!empty($quote) && $quote->getIsActive()) {
            $quote->setIsActive(false);
            ObjectManager::getInstance()->get(QuoteRepository::class)->save($quote);
        }
    }

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
            try {
                $order = ObjectManager::getInstance()->get(OrderInterface::class)->loadByAttribute('increment_id', $paymentIntentFromApi->getMerchantOrderId());
            } catch (Exception $e) {
            }

            $paymentIntentRecord = ObjectManager::getInstance()->get(PaymentIntentRepository::class)->getByIntentId($paymentIntentFromApi->getId());
            $detail = json_decode($paymentIntentRecord->getDetail(), true);
            $payment = $paymentMethod ?: $quote->getPayment();
            if ($payment->getMethod() === Vault::CODE) {
                $payment->setMethod(CardMethod::CODE);
            }

            $this->setAgreementIds($payment, $detail['agreement']);
            if (empty($order) || empty($order->getId())) {
                $this->checkIntent($paymentIntentFromApi, $quote);
                /** @var StructPaymentIntent $paymentIntent */
                $paymentIntentFromApi = ObjectManager::getInstance()->get(RetrievePaymentIntent::class)->setPaymentIntentId($paymentIntentFromApi->getId())->send();
                ObjectManager::getInstance()->get(IntentHelper::class)->setIntent($paymentIntentFromApi);
                if ($detail['uid']) {
                    $orderId = ObjectManager::getInstance()->get(PaymentInformationManagementInterface::class)->savePaymentInformationAndPlaceOrder(
                        $quoteId,
                        $payment,
                        $billingAddress
                    );
                } else {
                    $cartId = ObjectManager::getInstance()->get(QuoteIdToMaskedQuoteIdInterface::class)->execute($quoteId);
                    $orderId = ObjectManager::getInstance()->get(GuestPaymentInformationManagementInterface::class)->savePaymentInformationAndPlaceOrder(
                        $cartId,
                        $paymentIntentFromApi->getCustomer()['email'] ?? '',
                        $payment,
                        $billingAddress
                    );
                }

                ObjectManager::getInstance()->get(PaymentIntentRepository::class)->updateOrderId($paymentIntentRecord, $orderId);
                $order = $this->getFreshOrder($orderId);
                $this->addAVSResultToOrder($order, $paymentIntentFromApi);
                try {
                    $this->checkCardDetail($paymentIntentFromApi, $order);
                } catch (Exception $e) {
                    $this->logError('checkCardDetail failed: ' . $e->getMessage());
                }
            }

            $this->deactivateQuote($quote);

            $this->logInfo("Successfully placed order for quote ID: $quoteId from $from.");
            $connection->commit();
            $this->setCheckoutSuccess($quoteId, $order);
        }   catch (Exception $e) {
            $connection->rollBack();
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            throw $e;
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
        return ObjectManager::getInstance()->get(CurrencySwitcherAvailableCurrencies::class)->get();
    }

    public function isOrderBeforePayment(): bool
    {
        return ObjectManager::getInstance()->get(Configuration::class)->isOrderBeforePayment();
    }

    public function trimPaymentMethodCode(string $code): string
    {
        $code = str_replace(AbstractMethod::PAYMENT_PREFIX, '', $code);
        return str_replace('airwallex_cc_', '', $code);
    }

    public function logError(string $message)
    {
        ObjectManager::getInstance()->get(LoggerInterface::class)->error($message);
    }

    public function logInfo(string $message)
    {
        ObjectManager::getInstance()->get(LoggerInterface::class)->debug($message);
    }

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
