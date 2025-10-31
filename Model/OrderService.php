<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Afterpay;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Klarna;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\BankTransfer;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Airwallex\Payments\Model\Methods\BankTransfer as BankTransferMethod;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\OrderServiceInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\CurrentPaymentMethodHelper;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\Payments\Model\Methods\AfterpayMethod;
use Airwallex\Payments\Model\Methods\ExpressCheckout;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\Methods\RedirectMethod;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\RedirectMethod as RedirectMethodConfiguration;
use Airwallex\Payments\Plugin\ReCaptchaValidationPlugin;
use Exception;
use Error;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\InputException;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Magento\CheckoutAgreements\Model\Checkout\Plugin\GuestValidation;
use Magento\CheckoutAgreements\Model\Checkout\Plugin\Validation;
use Magento\AdminNotification\Model\Inbox;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;

class OrderService implements OrderServiceInterface
{
    use HelperTrait;

    protected PaymentConsentsInterface $paymentConsents;
    protected PaymentIntents $paymentIntents;
    protected Configuration $configuration;
    protected CheckoutData $checkoutHelper;
    protected CurrentPaymentMethodHelper $currentPaymentMethodHelper;
    protected GuestPaymentInformationManagementInterface $guestPaymentInformationManagement;
    protected PaymentInformationManagementInterface $paymentInformationManagement;
    protected PlaceOrderResponseInterfaceFactory $placeOrderResponseFactory;
    protected CacheInterface $cache;
    private CartRepositoryInterface $quoteRepository;
    private ReCaptchaValidationPlugin $reCaptchaValidationPlugin;
    public PaymentIntentRepository $paymentIntentRepository;
    private TransactionRepositoryInterface $transactionRepository;
    private OrderManagementInterface $orderManagement;
    private IsOrderCreatedHelper $isOrderCreatedHelper;
    private OrderResourceInterface $orderResource;
    private Confirm $confirm;
    protected Validation $agreementValidation;
    protected GuestValidation $agreementGuestValidation;
    private Inbox $inbox;
    private CommonLibraryInit $commonLibraryInit;
    private QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    private UrlInterface $url;
    private RetrievePaymentIntent $retrievePaymentIntent;

    public function __construct(
        PaymentConsentsInterface                   $paymentConsents,
        PaymentIntents                             $paymentIntents,
        Configuration                              $configuration,
        CheckoutData                               $checkoutHelper,
        CurrentPaymentMethodHelper                 $currentPaymentMethodHelper,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface      $paymentInformationManagement,
        PlaceOrderResponseInterfaceFactory         $placeOrderResponseFactory,
        CacheInterface                             $cache,
        CartRepositoryInterface                    $quoteRepository,
        ReCaptchaValidationPlugin                  $reCaptchaValidationPlugin,
        PaymentIntentRepository                    $paymentIntentRepository,
        TransactionRepositoryInterface             $transactionRepository,
        OrderManagementInterface                   $orderManagement,
        IsOrderCreatedHelper                       $isOrderCreatedHelper,
        OrderResourceInterface                     $orderResource,
        Confirm                                    $confirm,
        Validation                                 $agreementValidation,
        GuestValidation                            $agreementGuestValidation,
        Inbox                                      $inbox,
        CommonLibraryInit                          $commonLibraryInit,
        QuoteIdToMaskedQuoteIdInterface            $quoteIdToMaskedQuoteId,
        UrlInterface                               $url,
        RetrievePaymentIntent                      $retrievePaymentIntent
    )
    {
        $this->paymentConsents = $paymentConsents;
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->currentPaymentMethodHelper = $currentPaymentMethodHelper;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->placeOrderResponseFactory = $placeOrderResponseFactory;
        $this->cache = $cache;
        $this->quoteRepository = $quoteRepository;
        $this->reCaptchaValidationPlugin = $reCaptchaValidationPlugin;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->orderManagement = $orderManagement;
        $this->isOrderCreatedHelper = $isOrderCreatedHelper;
        $this->orderResource = $orderResource;
        $this->confirm = $confirm;
        $this->agreementValidation = $agreementValidation;
        $this->agreementGuestValidation = $agreementGuestValidation;
        $this->inbox = $inbox;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->url = $url;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $commonLibraryInit->exec();
    }

    /**
     * Guest place order
     *
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $paymentMethodId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function airwallexGuestPlaceOrder(
        string           $cartId,
        string           $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        return $this->savePaymentOrPlaceOrder($cartId, $paymentMethod, $billingAddress, $intentId, $email, $paymentMethodId, $from);
    }

    /**
     * Place order
     *
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $paymentMethodId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function airwallexPlaceOrder(
        string           $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        return $this->savePaymentOrPlaceOrder($cartId, $paymentMethod, $billingAddress, $intentId, '', $paymentMethodId, $from);
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     */
    protected function checkAgreements($quote, PaymentInterface $paymentMethod, $cartId, $email)
    {
        if ($paymentMethod->getMethod() === ExpressCheckout::CODE) {
            return;
        }
        if ($quote->getCustomer()->getId()) {
            $this->agreementValidation->beforeSavePaymentInformationAndPlaceOrder(
                $this->paymentInformationManagement,
                $cartId,
                $paymentMethod
            );
        } else {
            $this->agreementGuestValidation->beforeSavePaymentInformationAndPlaceOrder(
                $this->guestPaymentInformationManagement,
                $cartId,
                $email,
                $paymentMethod
            );
        }
    }

    /**
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws InputException
     */
    private function savePaymentOrPlaceOrder(
        string           $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $email = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        try {
            /** @var PlaceOrderResponse $response */
            $response = $this->placeOrderResponseFactory->create();
            $quote = $this->checkoutHelper->getQuote();
            $cartId = $quote->getId();
            if (empty($cartId)) {
                if (empty($intentId)) {
                    throw new LocalizedException(__('Cart cannot be empty.'));
                }
                $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
                return $response->setData([
                    'response_type' => 'success',
                    'order_id' => $paymentIntent->getOrderId()
                ]);
            }
            if (!$quote->getCustomer()->getId()) {
                $cartId = $this->quoteIdToMaskedQuoteId->execute($cartId);
            }

            $this->checkAgreements($quote, $paymentMethod, $cartId, $email);

            if (!$intentId) {
                if (!$this->configuration->isOrderBeforePayment()) {
                    return $this->requestIntent($quote, $paymentMethod, $email, $paymentMethodId, $from, $response);
                }
                $this->isOrderCreatedHelper->setIsCreated(false);
                return $this->orderThenIntent($quote, $cartId, $paymentMethod, $billingAddress, $email, $paymentMethodId, $from, $response);
            }

            try {
                $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
                $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();

                if ($this->configuration->isOrderBeforePayment()) {
                    $this->changeOrderStatus($paymentIntentFromApi, $paymentIntent->getOrderId(), $quote, __METHOD__);
                } else {
                    $this->placeOrder($paymentMethod, $paymentIntentFromApi, $quote, __METHOD__, $billingAddress);
                }
            } catch (Exception $e) {
                if (!$this->configuration->isOrderBeforePayment()) {
                    if (!empty($paymentIntent) && $paymentIntent->getDetail()) {
                        $detail = json_decode($paymentIntent->getDetail(), true);
                        if (empty($detail['is_order_failure_reported'])) {
                            $detail['is_order_failure_reported'] = true;
                            $this->paymentIntentRepository->updateDetail($paymentIntent, json_encode($detail));
                            $this->inbox->addCritical(
                                __('Payment Successful, Order Placement Failed'),
                                __("A customer has successfully completed the payment, but the order creation failed. Intent ID: $intentId."),
                            );
                        }
                    }
                }
                $tip = $this->configuration->isOrderBeforePayment() ? 'Order status change' : 'Order place';
                $message = trim($e->getMessage(), ' .') . '. ' . $tip . ' failed. Please try again.';
                RemoteLog::error(__METHOD__ . ': ' . $message, 'onOrderConfirmationError');
                return $response->setData([
                    'response_type' => 'error',
                    'message' => __($message),
                ]);
            }

            if ($this->configuration->isCardVaultActive() && $from === 'card_with_saved') {
                try {
                    $this->paymentConsents->syncVault($quote->getCustomer()->getId());
                } catch (Exception $e) {
                    RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onSyncSaveCardError');
                }
            }
        } catch (Exception | Error $e) {
            RemoteLog::error('OrderService exception: ' . $e->getMessage(), 'onCallFunctionSavePaymentOrPlaceOrderError');
            throw new LocalizedException(__($e->getMessage()));
        }

        $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
        return $response->setData([
            'response_type' => 'success',
            'order_id' => $paymentIntent->getOrderId()
        ]);
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws InputException
     */
    public function requestIntent($model, PaymentInterface $paymentMethod, ?string $email, ?string $paymentMethodId, ?string $from, PlaceOrderResponse $response): PlaceOrderResponse
    {
        $this->setCurrentPaymentMethod($paymentMethod, $from);

        $quote = $this->checkoutHelper->getQuote();
        $getPhoneAddress = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $phone = $getPhoneAddress->getTelephone() ?? '';
        $argEmail = $quote->getCustomer()->getId() ? $quote->getCustomerEmail() : $email;
        $intent = $this->paymentIntents->getIntent($model, $phone, $argEmail, $from, $paymentMethod);
        return $this->responseByRequestIntent($paymentMethodId, $intent, $paymentMethod, $model, $response, $email);
    }

    public function setCurrentPaymentMethod(PaymentInterface $paymentMethod, $from)
    {
        $method = $paymentMethod->getMethod();
        $method = $this->trimPaymentMethodCode($method);
        $method = $method === 'card' ? 'credit_card' : $method;
        $method = $method === 'express' ? $from : $method;
        $this->currentPaymentMethodHelper->setPaymentMethod($method);
    }

    /**
     * @param Quote $quote
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $email
     * @param string|null $paymentMethodId
     * @param string|null $from
     * @param PlaceOrderResponse $response
     * @return PlaceOrderResponse
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws AlreadyExistsException|JsonException
     * @throws LocalizedException
     * @throws Exception
     */
    public function orderThenIntent(Quote $quote, string $cartId, PaymentInterface $paymentMethod, ?AddressInterface $billingAddress, ?string $email, ?string $paymentMethodId, ?string $from, PlaceOrderResponse $response): PlaceOrderResponse
    {
        $order = $this->getOrderByQuote($quote);

        if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT
            || !$this->isOrderEqualToQuote($order, $quote, $billingAddress)) {
            try {
                if ($quote->getCustomer()->getId()) {
                    $orderId = $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                        $cartId,
                        $paymentMethod,
                        $billingAddress
                    );
                } else {
                    $orderId = $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                        $cartId,
                        $email,
                        $paymentMethod,
                        $billingAddress
                    );
                }
            } catch (Exception $e) {
                $message = 'Order failed: ' . trim($e->getMessage());
                RemoteLog::error(__METHOD__ . ': ' . $message, 'onSavePaymentInformationAndPlaceOrderError');
                throw $e;
            }

            $order = $this->getFreshOrder($orderId);

            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
            $this->orderResource->save($order);
            $this->addComment($order, '');
        } else {
            $payment = $order->getPayment();
            if ($payment->getMethod() !== $paymentMethod->getMethod()) {
                $payment->setMethod($paymentMethod->getMethod());
                $order->setPayment($payment);
                $this->orderResource->save($order);
            }
        }

        $requestIntentResponse = $this->requestIntent($order, $paymentMethod, $email, $paymentMethodId, $from, $response);
        if ($paymentMethod->getMethod() === BankTransferMethod::CODE && $quote && $quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }
        return $requestIntentResponse;
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    private function appendPaymentMethodId(?string $paymentMethodId, string $intentId): void
    {
        if ($paymentMethodId) {
            $record = $this->paymentIntentRepository->getByIntentId($intentId);
            $detail = $record->getDetail();
            $detailArray = $detail ? json_decode($detail, true) : [];
            if (empty($detailArray['payment_method_ids'])) $detailArray['payment_method_ids'] = [];
            $detailArray['payment_method_ids'][] = $paymentMethodId;
            $this->paymentIntentRepository->updateDetail($record, json_encode($detailArray));
        }
    }

    /**
     * @param Order $order
     * @param Quote $quote
     * @param ?AddressInterface $billingAddress
     * @return bool
     */
    public function isOrderEqualToQuote(Order $order, Quote $quote, ?AddressInterface $billingAddress): bool
    {
        $quoteAddr = $quote->getShippingAddress();
        $orderAddr = $order->getShippingAddress();
        if ($quote->isVirtual() && $orderAddr) return false;
        if (!$quote->isVirtual()) {
            if (!$quoteAddr || !$orderAddr) return false;
            if (!$this->isQuoteAddressSameAsOrderAddress($quoteAddr, $orderAddr)) return false;
            $method1 = $quoteAddr->getShippingMethod();
            $method2 = $order->getShippingMethod();
            if ((string)$method1 !== (string)$method2) return false;
        }

        if (!$billingAddress) {
            $billingAddress = $quote->getBillingAddress();
        }
        $quoteAddr = $billingAddress;
        $orderAddr = $order->getBillingAddress();
        if ($quoteAddr && !$orderAddr) return false;
        if (!$quoteAddr && $orderAddr) return false;
        if ($quoteAddr && $orderAddr) {
            /* @var OrderAddress $orderAddr */
            if (!$this->isQuoteAddressSameAsOrderAddress($quoteAddr, $orderAddr)) return false;
        }

        return $order->getId()
            && $this->isAmountEqual($order->getGrandTotal(), $quote->getGrandTotal())
            && $order->getOrderCurrencyCode() === $quote->getQuoteCurrencyCode()
            && $this->paymentIntents->getProductsForCompare($this->getProducts($order)) === $this->paymentIntents->getProductsForCompare($this->getProducts($quote));
    }

    public function isQuoteAddressSameAsOrderAddress(Address $quoteAddr, OrderAddress $orderAddr): bool
    {
        if ((string)$quoteAddr->getFirstname() !== (string)$orderAddr->getFirstname()) return false;
        if ((string)$quoteAddr->getLastname() !== (string)$orderAddr->getLastname()) return false;
        if ((string)$quoteAddr->getCompany() !== (string)$orderAddr->getCompany()) return false;
        if ((string)$quoteAddr->getRegion() !== (string)$orderAddr->getRegion()) return false;
        if (intval($quoteAddr->getRegionId()) !== intval($orderAddr->getRegionId())) return false;
        if ((string)$quoteAddr->getCountryId() !== (string)$orderAddr->getCountryId()) return false;
        if ((string)$quoteAddr->getCity() !== (string)$orderAddr->getCity()) return false;
        $street1 = implode(', ', $quoteAddr->getStreet());
        $street2 = implode(', ', $orderAddr->getStreet());
        if ($street1 !== $street2) return false;
        if ((string)$quoteAddr->getPostcode() !== (string)$orderAddr->getPostcode()) return false;
        if ((string)$quoteAddr->getTelephone() !== (string)$orderAddr->getTelephone()) return false;
        return true;
    }

    /**
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws Exception
     */
    public function getAirwallexPaymentsNextAction(StructPaymentIntent $intent, PaymentInterface $paymentMethod, string $browserInformation, $address, $email = "")
    {
        if (empty($intent->getId())) {
            throw new Exception('Intent id is required.');
        }

        $paymentIntentFromDB = $this->paymentIntentRepository->getByIntentId($intent->getId());
        /** @var StructPaymentIntent $paymentIntentFromApi */
        $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intent->getId())->send();
        if ($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured()) {
            $quote = $this->quoteRepository->get($paymentIntentFromDB->getQuoteId());
            if ($this->isOrderBeforePayment()) {
                $this->changeOrderStatus($paymentIntentFromApi, $paymentIntentFromDB->getOrderId(), $quote, __METHOD__);
            } else {
                $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
            }
            return json_encode([
                'type' => 'redirect',
                'url' => $this->url->getUrl('checkout/onepage/success')
            ]);
        }

        $currentPaymentMethodCode = $paymentMethod->getMethod();
        $cacheName = $currentPaymentMethodCode . '-qrcode-' . $intent->getId();
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $cacheName .= '-' . md5($_SERVER['HTTP_USER_AGENT']);
        }
        try {
            $currencySwitcherData = $this->switchCurrency($intent, $paymentMethod, $address);
        } catch (Exception $exception) {
            throw new LocalizedException(__($exception->getMessage()));
        } catch (Error $error) {
            throw new LocalizedException(__($error->getMessage()));
        }
        if (!empty($currencySwitcherData['quote_id'])) {
            $cacheName .= '-' . $currencySwitcherData['quote_id'];
        }
        if (!$returnUrl = $this->cache->load($cacheName)) {
            try {
                $confirmRequest = $this->confirm->setPaymentIntentId($intent->getId())->setBrowserInformation($browserInformation);
                if (!empty($currencySwitcherData['target_currency'])) {
                    $confirmRequest = $confirmRequest->setQuote($currencySwitcherData['target_currency'], $currencySwitcherData['quote_id'] ?? null);
                }
                $confirmResponse = $confirmRequest->setInformation($this->getPaymentMethodCode($currentPaymentMethodCode), $address, $intent, $email, $currencySwitcherData)->send();
            } catch (Exception $exception) {
                throw new LocalizedException(__($exception->getMessage()));
            }

            $returnUrl = json_encode($confirmResponse);
            $this->cache->save($returnUrl, $cacheName, [], 300);
        }
        return $returnUrl;
    }

    /**
     * @throws LocalizedException
     * @throws JsonException
     * @throws GuzzleException
     */
    protected function switchCurrency(StructPaymentIntent $intent, PaymentInterface $paymentMethod, $address): array
    {
        $baseCurrency = $intent->getBaseCurrency() ?: $intent->getCurrency();
        $availableCurrencies = json_decode($this->getAvailableCurrencies(), true);
        if (empty($availableCurrencies)) {
            return [];
        }
        $billingCountry = !empty($address) ? $address->getCountryId() : '';
        $currentPaymentMethodCode = $paymentMethod->getMethod();
        if ($currentPaymentMethodCode === KlarnaMethod::CODE) {
            if (!in_array($billingCountry, array_keys(Klarna::SUPPORTED_COUNTRY_TO_CURRENCY), true)) {
                throw new LocalizedException(__('Klarna is not available in your country. Please change your billing address to a compatible country or choose a different payment method.'));
            }
            $targetCurrency = Klarna::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry];
            if ($baseCurrency === $targetCurrency) {
                return $baseCurrency !== $intent->getCurrency() ? [
                    'target_currency' => $baseCurrency,
                ] : [];
            }
            $brand = 'Klarna';
        } elseif ($currentPaymentMethodCode === AfterpayMethod::CODE) {
            $entity = $this->account() ? $this->account()->getOwningEntity() : '';
            $entityCurrencies = Afterpay::SUPPORTED_ENTITY_TO_CURRENCIES[$entity] ?? [];
            if (empty($entityCurrencies)) {
                return [];
            }

            if (count($entityCurrencies) === 1) {
                $targetCurrency = $entityCurrencies[0];
            } else {
                if (!empty(Afterpay::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry])
                    && in_array(Afterpay::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry], $entityCurrencies, true)) {
                    $targetCurrency = Afterpay::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry];
                } else {
                    if (empty($paymentMethod->getAdditionalData())) {
                        return [];
                    }
                    $afterpayCountry = $paymentMethod->getAdditionalData()['afterpay_country'] ?? '';
                    if (empty($afterpayCountry)) {
                        return [];
                    }
                    $targetCurrency = Afterpay::SUPPORTED_COUNTRY_TO_CURRENCY[$afterpayCountry] ?? '';
                }
            }
            if ($baseCurrency === $targetCurrency) {
                return $baseCurrency !== $intent->getCurrency() ? [
                    'target_currency' => $baseCurrency,
                ] : [];
            }
            $brand = 'Afterpay';
        } elseif ($currentPaymentMethodCode === BankTransferMethod::CODE) {
            $targetCurrency = "";
            if (isset(BankTransfer::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry])) {
                $targetCurrency = BankTransfer::SUPPORTED_COUNTRY_TO_CURRENCY[$billingCountry];
            }
            if (empty($targetCurrency) && !empty($paymentMethod->getAdditionalData())) {
                $targetCurrency = $paymentMethod->getAdditionalData()['bank_transfer_currency'] ?? '';
            }
            if ($baseCurrency === $targetCurrency) {
                return $baseCurrency !== $intent->getCurrency() ? [
                    'target_currency' => $baseCurrency,
                ] : [];
            }
            $brand = 'Bank Transfer';
        } else {
            $entity = $this->account()->getOwningEntity();
            $paymentMethodCode = $this->getPaymentMethodCode($currentPaymentMethodCode);
            $entityCurrencies = RedirectMethodConfiguration::SUPPORTED_ENTITY_TO_CURRENCY[$paymentMethodCode][$entity] ?? [];
            if (empty($entityCurrencies)) {
                throw new LocalizedException(__('The selected payment method is not supported.'));
            }
            if (count($entityCurrencies) === 1) {
                $targetCurrency = $entityCurrencies[0];
                if ($baseCurrency === $targetCurrency) {
                    return $baseCurrency !== $intent->getCurrency() ? [
                        'target_currency' => $baseCurrency,
                    ] : [];
                }
            } else {
                if (empty($paymentMethod->getAdditionalData())) {
                    $targetCurrency = RedirectMethodConfiguration::DEFAULT_CURRENCY[$paymentMethodCode] ?? '';
                    if ($baseCurrency === $targetCurrency) {
                        return $baseCurrency !== $intent->getCurrency() ? [
                            'target_currency' => $baseCurrency,
                        ] : [];
                    }
                } else {
                    $targetCurrency = $paymentMethod->getAdditionalData()['redirect_method_chosen_currency'] ?? '';
                    if ($baseCurrency === $targetCurrency || empty($targetCurrency) || !in_array($targetCurrency, $entityCurrencies, true)) {
                        return $baseCurrency !== $intent->getCurrency() ? [
                            'target_currency' => $baseCurrency,
                        ] : [];
                    }
                }
            }
            $brand = RedirectMethod::displayNames()[$currentPaymentMethodCode];
        }
        if (empty($targetCurrency)) {
            throw new LocalizedException(__('Invalid request, target currency is required.'));
        }
        $baseAmount = $intent->getBaseAmount() ?: $intent->getAmount();
        if (empty($baseCurrency) || empty($baseAmount)) {
            throw new LocalizedException(__('Invalid request, intent information is required.'));
        }
        if (!in_array($targetCurrency, $availableCurrencies, true) || !in_array($baseCurrency, $availableCurrencies, true)) {
            throw new LocalizedException(__('%1 is not available in your country. Please change your billing address to a compatible country or choose a different payment method.', $brand));
        }
        $switcher = $this->getCurrencySwitcher($baseCurrency, $targetCurrency, $baseAmount);
        if (empty($switcher) || empty($switcher->getId())) {
            throw new LocalizedException(__($brand . ' is not available in your country. Please change your billing address to a compatible country or choose a different payment method.'));
        }
        return [
            'quote_id' => $switcher->getId(),
            'target_currency' => $switcher->getTargetCurrency(),
        ];
    }

    /**
     * @param string|null $paymentMethodId
     * @param StructPaymentIntent $intent
     * @param PaymentInterface $paymentMethod
     * @param $model
     * @param PlaceOrderResponse $response
     * @param string $email
     * @return PlaceOrderResponse
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function responseByRequestIntent(?string $paymentMethodId, StructPaymentIntent $intent, PaymentInterface $paymentMethod, $model, PlaceOrderResponse $response, string $email = ""): PlaceOrderResponse
    {
        $this->appendPaymentMethodId($paymentMethodId, $intent->getId());

        $data = [
            'response_type' => 'confirmation_required',
            'intent_id' => $intent->getId(),
            'client_secret' => $intent->getClientSecret(),
        ];
        if ($this->isRedirectMethodConstant($paymentMethod->getMethod())) {
            $browserInformation = '';
            if (!empty($paymentMethod->getAdditionalData())) {
                $browserInformation = $paymentMethod->getAdditionalData()['browser_information'] ?? "";
            }
            $data['next_action'] = $this->getAirwallexPaymentsNextAction($intent, $paymentMethod, $browserInformation, $model->getBillingAddress(), $email);
        }

        $this->cache->save(1, $this->reCaptchaValidationPlugin->getCacheKey($intent->getId()), [], 3600);
        return $response->setData($data);
    }
}
