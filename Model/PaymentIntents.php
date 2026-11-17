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
namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\Payments\Admin\Cards\Api\CompanyConsentsInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Create as CreatePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Cancel as CancelPaymentIntent;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteRepository;
use Exception;
use JsonException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CacheInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;

class PaymentIntents
{
    use HelperTrait;

    const CURRENCY_TO_DECIMAL = StructPaymentIntent::CURRENCY_TO_DECIMAL;

    protected PaymentConsentsInterface $paymentConsents;
    private CreatePaymentIntent $createPaymentIntent;
    private Session $checkoutSession;
    private QuoteRepository $quoteRepository;
    private UrlInterface $urlInterface;
    private PaymentIntentRepository $paymentIntentRepository;
    private OrderFactory $orderFactory;
    private OrderResourceInterface $orderResource;
    private RetrievePaymentIntent $retrievePaymentIntent;

    /**
     * Constructor
     */
    public function __construct(
        PaymentConsentsInterface $paymentConsents,
        CreatePaymentIntent      $createPaymentIntent,
        Session                  $checkoutSession,
        QuoteRepository          $quoteRepository,
        UrlInterface             $urlInterface,
        PaymentIntentRepository  $paymentIntentRepository,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource,
        RetrievePaymentIntent    $retrievePaymentIntent
    )
    {
        $this->paymentConsents = $paymentConsents;
        $this->createPaymentIntent = $createPaymentIntent;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->urlInterface = $urlInterface;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
    }

    /**
     * @throws Exception
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function createIntent($model, string $phone, string $email, string $from, PaymentInterface $paymentMethod): StructPaymentIntent
    {
        $isOrder = $model instanceof Order;

        $entityId = (int) $model->getId();
        $scope = $isOrder ? ReturnState::SCOPE_ORDER : ReturnState::SCOPE_QUOTE;
        $uri = 'airwallex/redirect?id=' . $entityId
            . '&type=' . ($isOrder ? ReturnState::SCOPE_ORDER : ReturnState::SCOPE_QUOTE)
            . '&state=' . urlencode($this->generateReturnState($scope, $entityId));

        if (!$isOrder && !$model->getReservedOrderId()) {
            $model->reserveOrderId();
            $this->quoteRepository->save($model);
        }

        $intentAmount = AmountHelper::formatAmount($model->getGrandTotal(), $this->getCurrencyCode($model));
        $products = $this->getProducts($model);
        $merchantOrderId = $isOrder ? $model->getIncrementId() : $model->getReservedOrderId();
        $sessionId = PaymentFlowLog::sessionIdFromPayment($paymentMethod);
        $createPaymentIntentRequest = $this->createPaymentIntent
            ->setMetadata($this->getMetadata())
            ->setReferrerDataType($this->getReferrerDataType($paymentMethod, $from))
            ->setOrder([
                'products' => $products,
                'shipping' => $this->getShippingAddress($model)
            ])
            ->setAmount($intentAmount)
            ->setCurrency($this->getCurrencyCode($model))
            ->setMerchantOrderId($merchantOrderId)
            ->setReturnUrl(trim($this->urlInterface->getUrl($uri), '/'));

        // Report the store origin (scheme + host) where the customer completes
        // the transaction as the root-level merchant_website_url (Mastercard
        // AN 6022). Prefer the browser origin the front-end sends in
        // additional_data; fall back to the configured store base URL (e.g.
        // for admin/API-created orders). Only sent when a valid origin resolves.
        $additionalData = $paymentMethod->getAdditionalData() ?? [];
        $clientOrigin = $additionalData['merchant_website_url'] ?? '';
        $merchantWebsiteUrl = UrlHelper::toOrigin((string) $clientOrigin);
        if ($merchantWebsiteUrl === '') {
            $merchantWebsiteUrl = UrlHelper::toOrigin((string) $this->urlInterface->getBaseUrl());
        }
        if ($merchantWebsiteUrl !== '') {
            $createPaymentIntentRequest->setMerchantWebsiteUrl($merchantWebsiteUrl);
        }

        try {
            $uid = $isOrder ? $model->getCustomerId() : ($model->getCustomer() ? $model->getCustomer()->getId() : null);
            $airwallexCustomerId = $this->getIntentCustomerId($model, $from);
            if ($airwallexCustomerId !== '') {
                $intent = $createPaymentIntentRequest->setCustomerId($airwallexCustomerId)->send();
            } else {
                $customer = [];
                if (!empty($email)) $customer['email'] = $email;
                if (!empty($phone)) $customer['phone_number'] = $phone;
                $intent = $createPaymentIntentRequest->setCustomer($customer)->send();
            }
        } catch (Exception $e) {
            RemoteLog::error( $e->getMessage(), RemoteLog::ON_PAYMENT_CREATION_ERROR);
            throw $e;
        }

        PaymentFlowLog::log(CheckoutFunnelEvent::buildMessage(
            'PAYMENT_INTENT_CREATED',
            $sessionId,
            PaymentFlowLog::paymentMethodName($paymentMethod, (string) $from),
            false,
            '',
            [
                'intent_id' => (string) $intent->getId(),
                'merchant_order_id' => (string) $merchantOrderId,
            ]
        ));

        $products = $this->getProducts($model);
        $shipping = $this->getShippingAddress($model);
        $billing = $this->getBillingAddress($model, $email);
        $agreementIds = $paymentMethod->getExtensionAttributes()->getAgreementIds();
        $agreement = $agreementIds ? json_encode($agreementIds) : "[]";
        $awx_session_id = $sessionId;

        $this->paymentIntentRepository->save(
            $isOrder ? $model->getIncrementId() : $model->getReservedOrderId(),
            $intent->getId(),
            $this->getCurrencyCode($model),
            $model->getGrandTotal(),
            $isOrder ? $model->getId() : 0,
            $isOrder ? $model->getQuoteId() : $model->getId(),
            $isOrder ? $model->getStore()->getId() : $model->getStoreId(),
            json_encode(compact('products', 'shipping', 'billing', 'email', 'uid', 'from', 'agreement', 'awx_session_id')),
            json_encode([$paymentMethod->getMethod()]),
        );

        return $this->retrievePaymentIntent->setPaymentIntentId($intent->getId())->send();
    }

    /**
     * @param $model
     * @param string $phone
     * @param string $email
     * @param string $from
     * @param PaymentInterface $paymentMethod
     * @return StructPaymentIntent
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws RequestException
     */
    public function getIntent($model, string $phone, string $email, string $from, PaymentInterface $paymentMethod): StructPaymentIntent
    {
        $isOrder = $model instanceof Order;
        if ($isOrder) {
            $paymentIntent = $this->paymentIntentRepository->getByOrderId($model->getId());
        } else {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($model->getId());
        }

        $isPaymentChanged = $this->paymentIntentRepository->lastMethodCode($paymentIntent) !== $paymentMethod->getMethod();
        $mustRecreate = !$paymentIntent || $this->isRequiredToGenerateIntent($model, $paymentIntent, $email) || $isPaymentChanged;
        if (!$paymentIntent) {
            return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
        }

        try {
            /** @var StructPaymentIntent $paymentIntentFromApi */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            $error = json_decode($e->getMessage(), true);
            if (is_array($error) && isset($error['code']) && $error['code'] === AbstractApi::ERROR_RESOURCE_NOT_FOUND) {
                return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
            }
            throw $e;
        }
        if (!$paymentIntentFromApi->getId() || $paymentIntentFromApi->getStatus() === StructPaymentIntent::STATUS_CANCELLED) {
            return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
        }
        if ($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured()) {
            $order = $this->paymentIntentRepository->getOrder($paymentIntentFromApi->getId());
            if ((int) $paymentIntent->getStoreId() === (int) $model->getStoreId()
                && $paymentIntentFromApi->getId() === $paymentIntent->getIntentId()
                && $this->canCompleteIntent($model, $paymentIntentFromApi)
                && (!$order || (!$order->isCanceled() && $this->canCompleteIntent($order, $paymentIntentFromApi)))
            ) {
                return $paymentIntentFromApi;
            }
            // Leave a mismatched previous payment for the merchant to reconcile/refund.
            return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
        }

        $airwallexCustomerId = $this->getIntentCustomerId($model, $from);
        // Intents created before login or customer binding must not be reused for another owner.
        $mustRecreate = $mustRecreate || ($airwallexCustomerId !== ''
            && $paymentIntentFromApi->getCustomerId() !== $airwallexCustomerId);

        $returnQuery = parse_url($paymentIntentFromApi->getReturnUrl(), PHP_URL_QUERY);
        $returnParams = [];
        parse_str(is_string($returnQuery) ? $returnQuery : '', $returnParams);
        $returnState = $returnParams['state'] ?? '';
        $scope = $isOrder ? ReturnState::SCOPE_ORDER : ReturnState::SCOPE_QUOTE;
        $mustRecreate = $mustRecreate || !is_string($returnState)
            || !$this->validateReturnState($returnState, $scope, (int) $model->getId());
        if ($mustRecreate) {
            try {
                ObjectManager::getInstance()->get(CacheInterface::class)
                    ->save(true, $this->cancelCacheName($paymentIntentFromApi->getId()), [], 3600);
                ObjectManager::getInstance()
                    ->get(CancelPaymentIntent::class)
                    ->setPaymentIntentId($paymentIntentFromApi->getId())
                    ->send();
            } catch (Exception $e) {
                if (strstr($e->getMessage(), 'CANCELLED') === false) {
                    throw new LocalizedException(__('Unable to replace the previous payment attempt. Please try again.'));
                }
            }
            return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
        }

        $this->paymentIntentRepository->appendMethodCode($paymentIntent, $paymentMethod->getMethod());
        $this->syncReusedIntentDetail($paymentIntent, $paymentMethod, $from);
        return $paymentIntentFromApi;
    }

    protected function getIntentCustomerId($model, string $from): string
    {
        $customerId = $model instanceof Order
            ? (int) $model->getCustomerId()
            : (int) ($model->getCustomer() ? $model->getCustomer()->getId() : 0);
        if (!$customerId) {
            return '';
        }
        if ($from === 'card_with_saved' && $this->isMiniPluginExists()) {
            $customerId = (int) ObjectManager::getInstance()->get(CompanyConsentsInterface::class)->getSuperId($customerId);
        }
        $airwallexCustomerId = $this->paymentConsents->createAirwallexCustomerById($customerId);
        if ($airwallexCustomerId === '') {
            throw new LocalizedException(__('Unable to initialize your payment customer. Please refresh checkout and try again.'));
        }
        return $airwallexCustomerId;
    }

    public function canCompleteIntent($model, StructPaymentIntent $intent): bool
    {
        $currency = $intent->getBaseCurrency() ?: $intent->getCurrency();
        $amount = $intent->getBaseCurrency() ? $intent->getBaseAmount() : $intent->getAmount();
        $orderId = $model instanceof Order ? $model->getIncrementId() : $model->getReservedOrderId();
        return ($intent->isAuthorized() || $intent->isCaptured())
            && (string) $orderId !== '' && $intent->getMerchantOrderId() === (string) $orderId
            && $currency === $this->getCurrencyCode($model)
            && AmountHelper::amountEquals($amount, $model->getGrandTotal(), $currency);
    }

    /**
     * Keep awx_session_id and from on the stored intent in sync when an
     * existing intent is reused by a subsequent attempt.
     *
     * @param PaymentIntent $paymentIntent
     * @param PaymentInterface $paymentMethod
     * @param string $from
     * @return void
     */
    private function syncReusedIntentDetail(PaymentIntent $paymentIntent, PaymentInterface $paymentMethod, string $from): void
    {
        try {
            $detail = json_decode((string) $paymentIntent->getDetail(), true);
            if (!is_array($detail)) {
                return;
            }
            $changed = false;
            $sessionId = PaymentFlowLog::sessionIdFromPayment($paymentMethod);
            if ($sessionId !== '' && ($detail['awx_session_id'] ?? '') !== $sessionId) {
                $detail['awx_session_id'] = $sessionId;
                $changed = true;
            }
            if ($from !== '' && ($detail['from'] ?? '') !== $from) {
                $detail['from'] = $from;
                $changed = true;
            }
            if ($changed) {
                $this->paymentIntentRepository->updateDetail($paymentIntent, json_encode($detail));
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Check whether a new PaymentIntent must be created
     *
     * @param Quote|Order $model
     * @param PaymentIntent $paymentIntent
     * @param string $email
     * @return bool
     */
    public function isRequiredToGenerateIntent($model, PaymentIntent $paymentIntent, string $email = ''): bool
    {
        if ($model instanceof Order) {
            $freshOrder = $this->orderFactory->create();
            $this->orderResource->load($freshOrder, $model->getId());
            if ($freshOrder->getStatus() !== Order::STATE_PENDING_PAYMENT) {
                return true;
            }
        }

        if ($this->getCurrencyCode($model) !== $paymentIntent->getCurrencyCode()) {
            return true;
        }

        if (!$this->isAmountEqual($model->getGrandTotal(), $paymentIntent->getGrandTotal(), $this->getCurrencyCode($model))) {
            return true;
        }

        if (!$paymentIntent->getDetail()) return true;
        $detail = json_decode($paymentIntent->getDetail(), true);
        if (empty($detail['products'])) return true;

        $products = $this->getProducts($model);
        if ($this->getProductsForCompare($products) !== $this->getProductsForCompare($detail['products'])) {
            return true;
        }

        $currentBilling = $this->getBillingAddress($model, $email);
        $cachedBilling = $detail['billing'] ?? [];
        $cachedEmail = (string) ($detail['email'] ?? ($cachedBilling['email'] ?? ''));
        if (array_key_exists('email', $detail) && $email !== (string) ($detail['email'] ?? '')) {
            return true;
        }
        $resolvedEmail = $email !== '' ? $email : (string) ($currentBilling['email'] ?? '');
        if ($resolvedEmail !== $cachedEmail) {
            return true;
        }
        if (($cachedBilling['email'] ?? '') === '' && $cachedEmail !== '') {
            $cachedBilling['email'] = $cachedEmail;
        }
        if ($this->getBillingAddressForCompare($currentBilling) !== $this->getBillingAddressForCompare($cachedBilling)) {
            return true;
        }

        $currentShipping = $this->getShippingAddress($model);
        $cachedShipping = $detail['shipping'] ?? null;
        if ($this->getShippingAddressForCompare($currentShipping) !== $this->getShippingAddressForCompare($cachedShipping)) {
            return true;
        }

        return false;
    }

    /**
     * Normalize product lines for intent reuse comparison
     *
     * @param array $products
     * @return string
     */
    public function getProductsForCompare($products): string
    {
        $filteredData = array_map(function ($item) {
            return [
                'code' => $item['code'] ?? '',
                'sku' => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 0
            ];
        }, $products);

        usort($filteredData, function ($a, $b) {
            if ($a['code'] === $b['code']) {
                return $a['sku'] <=> $b['sku'];
            }
            return $a['code'] <=> $b['code'];
        });
        return json_encode($filteredData);
    }

    /**
     * Normalize a billing address for intent reuse comparison
     *
     * @param mixed $billing
     * @return string
     */
    public function getBillingAddressForCompare($billing): string
    {
        $billing = $billing ?? []; 
        $filteredData = [
            'first_name' => $billing['first_name'] ?? '',
            'last_name' => $billing['last_name'] ?? '',
            'email' => $billing['email'] ?? '',
            'phone_number' => $billing['phone_number'] ?? '',
            'country_code' => $billing['address']['country_code'] ?? '',
            'state' => $billing['address']['state'] ?? '',
            'city' => $billing['address']['city'] ?? '',
            'street' => $billing['address']['street'] ?? '',
            'postcode' => $billing['address']['postcode'] ?? '',
        ];
        return json_encode($filteredData);
    }

    public function getShippingAddressForCompare($shipping): string
    {
        if (empty($shipping) || !is_array($shipping)) {
            return '';
        }
        $filteredData = [
            'first_name' => $shipping['first_name'] ?? '',
            'last_name' => $shipping['last_name'] ?? '',
            'phone_number' => $shipping['phone_number'] ?? '',
            'shipping_method' => $shipping['shipping_method'] ?? '',
            'country_code' => $shipping['address']['country_code'] ?? '',
            'state' => $shipping['address']['state'] ?? '',
            'city' => $shipping['address']['city'] ?? '',
            'street' => $shipping['address']['street'] ?? '',
            'postcode' => $shipping['address']['postcode'] ?? '',
        ];
        return json_encode($filteredData);
    }
}
