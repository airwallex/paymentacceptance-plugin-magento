<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\OrderServiceInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\CurrentPaymentMethodHelper;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;
use Airwallex\Payments\Model\Methods\AfterpayMethod;
use Airwallex\Payments\Model\Methods\BankTransfer;
use Airwallex\Payments\Model\Methods\ExpressCheckout;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\Methods\RedirectMethod;
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
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Magento\Framework\Exception\InputException;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\CheckoutAgreements\Model\Checkout\Plugin\GuestValidation;
use Magento\CheckoutAgreements\Model\Checkout\Plugin\Validation;
use Magento\AdminNotification\Model\Inbox;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order\Address as OrderAddress;

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
    protected Get $intentGet;
    private CartRepositoryInterface $quoteRepository;
    private ReCaptchaValidationPlugin $reCaptchaValidationPlugin;
    protected ErrorLog $errorLog;
    public PaymentIntentRepository $paymentIntentRepository;
    private TransactionRepositoryInterface $transactionRepository;
    private OrderManagementInterface $orderManagement;
    private IsOrderCreatedHelper $isOrderCreatedHelper;
    private OrderResourceInterface $orderResource;
    private Confirm $confirm;
    private IntentHelper $intentHelper;
    protected Validation $agreementValidation;
    protected GuestValidation $agreementGuestValidation;
    private Inbox $inbox;

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
        Get                                        $intentGet,
        CartRepositoryInterface                    $quoteRepository,
        ReCaptchaValidationPlugin                  $reCaptchaValidationPlugin,
        ErrorLog                                   $errorLog,
        PaymentIntentRepository                    $paymentIntentRepository,
        TransactionRepositoryInterface             $transactionRepository,
        OrderManagementInterface                   $orderManagement,
        IsOrderCreatedHelper                       $isOrderCreatedHelper,
        OrderResourceInterface                     $orderResource,
        Confirm                                    $confirm,
        IntentHelper                               $intentHelper,
        Validation                                 $agreementValidation,
        GuestValidation                            $agreementGuestValidation,
        Inbox                                      $inbox
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
        $this->intentGet = $intentGet;
        $this->quoteRepository = $quoteRepository;
        $this->reCaptchaValidationPlugin = $reCaptchaValidationPlugin;
        $this->errorLog = $errorLog;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->orderManagement = $orderManagement;
        $this->isOrderCreatedHelper = $isOrderCreatedHelper;
        $this->orderResource = $orderResource;
        $this->confirm = $confirm;
        $this->intentHelper = $intentHelper;
        $this->agreementValidation = $agreementValidation;
        $this->agreementGuestValidation = $agreementGuestValidation;
        $this->inbox = $inbox;
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
        AddressInterface $billingAddress = null,
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
        AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $paymentMethodId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        return $this->savePaymentOrPlaceOrder($cartId, $paymentMethod, $billingAddress, $intentId, '', $paymentMethodId, $from);
    }

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
        AddressInterface $billingAddress = null,
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
                $resp = $this->intentGet->setPaymentIntentId($intentId)->send();

                $intentResponse = json_decode($resp, true);

                if ($this->configuration->isOrderBeforePayment()) {
                    $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote);
                } else {
                    $this->checkIntent($intentResponse, $quote);
                    $this->intentHelper->setIntent($intentResponse);
                    $this->placeOrder($paymentMethod, $intentResponse, $quote, self::class, $billingAddress);
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
                $this->errorLog->setMessage($message, $e->getTraceAsString(), $intentId)->send();
                return $response->setData([
                    'response_type' => 'error',
                    'message' => __($message),
                ]);
            }

            if ($this->configuration->isCardVaultActive() && $from === 'card_with_saved') {
                try {
                    $this->paymentConsents->syncVault($quote->getCustomer()->getId());
                } catch (Exception $e) {
                    $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $intentId)->send();
                }
            }
        } catch (Exception | Error $e) {
            $this->errorLog->setMessage('OrderService exception: ' . $e->getMessage(), $e->getTraceAsString(), $intentId)->send();
            throw new LocalizedException(__($e->getMessage()), $e);
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
                $this->errorLog->setMessage($message, $e->getTraceAsString(), $paymentMethod->getMethod())->send();
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
        if ($paymentMethod->getMethod() === BankTransfer::CODE && $quote && $quote->getIsActive()) {
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
    public function getAirwallexPaymentsNextAction(array $intent, PaymentInterface $paymentMethod, string $browserInformation, $address, $email = "")
    {
        if (!$intent['id']) {
            throw new Exception('Intent id is required.');
        }
        $code = $paymentMethod->getMethod();
        $cacheName = $code . '-qrcode-' . $intent['id'];
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $cacheName .= '-' . $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$returnUrl = $this->cache->load($cacheName)) {
            try {
                $request = $this->confirm->setPaymentIntentId($intent['id'])->setBrowserInformation($browserInformation);
                $currencySwitcherData = [];
                if (in_array($code, RedirectMethod::CURRENCY_SWITCHER_METHODS, true)) {
                    $currencySwitcherData = $this->switchCurrency($intent, $paymentMethod, $address);
                    if (!empty($currencySwitcherData['quote_id'])) {
                        $request = $request->setQuote($currencySwitcherData['target_currency'], $currencySwitcherData['quote_id']);
                    }
                }
                $resp = $request->setInformation($this->getPaymentMethodCode($code), $address, $email, $intent, $currencySwitcherData)->send();
            } catch (Exception $exception) {
                throw new LocalizedException(__($exception->getMessage()));
            }

            $returnUrl = json_encode($resp);
            $this->cache->save($returnUrl, $cacheName, [], 300);
        }
        return $returnUrl;
    }

    /**
     * @throws LocalizedException
     * @throws JsonException
     * @throws GuzzleException
     */
    protected function switchCurrency(array $intent, PaymentInterface $paymentMethod, $address): array
    {
        $currencies = json_decode($this->getAvailableCurrencies(), true);
        if (empty($currencies)) {
            return [];
        }
        $country = !empty($address) ? $address->getCountryId() : '';
        $code = $paymentMethod->getMethod();
        $brand = '';
        if ($code === KlarnaMethod::CODE) {
            if (!in_array($country, array_keys(KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY), true)) {
                throw new LocalizedException(__('Klarna is not available in your country. Please change your billing address to a compatible country or choose a different payment method.'));
            }
            $targetCurrency = KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country];
            if ($targetCurrency === $intent['currency']) {
                return [];
            }
            $brand = 'Klarna';
        } elseif ($code === AfterpayMethod::CODE) {
            $account = $this->account();
            $arr = json_decode($account, true);
            $entity = $arr['owningEntity'];
            $entityCurrencies = AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY[$entity] ?? [];
            if (empty($entityCurrencies)) {
                throw new LocalizedException(__('The selected payment method is not supported.'));
            }

            if (count($entityCurrencies) === 1) {
                if (in_array($intent['currency'], $entityCurrencies, true)) {
                    return [];
                }
                $targetCurrency = $entityCurrencies[0];
            } else {
                if (empty($paymentMethod->getAdditionalData())) {
                    return [];
                }
                $afterpayCountry = $paymentMethod->getAdditionalData()['afterpay_country'] ?? '';
                if (empty($afterpayCountry)) {
                    return [];
                }
                if (!empty(AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country])
                    && in_array(AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country], $entityCurrencies, true)) {
                    $targetCurrency = AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$country];
                } else {
                    $targetCurrency = AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY[$afterpayCountry] ?? '';
                }
                if ($targetCurrency === $intent['currency']) {
                    return [];
                }
            }
            $brand = 'Afterpay';
        } elseif ($code === BankTransfer::CODE) {
            $targetCurrency = "";
            if (isset(BankTransfer::SUPPORTED_COUNTRY_TO_CURRENCY[$country])) {
                $targetCurrency = BankTransfer::SUPPORTED_COUNTRY_TO_CURRENCY[$country];
            }
            if (empty($targetCurrency) && !empty($paymentMethod->getAdditionalData())) {
                $targetCurrency = $paymentMethod->getAdditionalData()['bank_transfer_currency'] ?? '';
            }
            if ($targetCurrency === $intent['currency']) {
                return [];
            }
            $brand = 'Bank Transfer';
        }
        if (empty($targetCurrency)) {
            throw new LocalizedException(__('Invalid request, target currency is required.'));
        }
        if (empty($intent['currency']) || empty($intent['amount'])) {
            throw new LocalizedException(__('Invalid request, intent information is required.'));
        }
        if (!in_array($targetCurrency, $currencies, true) || !in_array($intent['currency'], $currencies, true)) {
            throw new LocalizedException(__('%1 is not available in your country. Please change your billing address to a compatible country or choose a different payment method.', $brand));
        }
        $res = $this->currencySwitcher($intent['currency'], $targetCurrency, $intent['amount']);
        $switcher = json_decode($res, true);
        if (empty($switcher['id'])) {
            throw new LocalizedException(__($brand . ' is not available in your country. Please change your billing address to a compatible country or choose a different payment method.'));
        }
        return [
            'quote_id' => $switcher['id'],
            'target_currency' => $targetCurrency,
        ];
    }

    /**
     * @param string|null $paymentMethodId
     * @param array $intent
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
    public function responseByRequestIntent(?string $paymentMethodId, array $intent, PaymentInterface $paymentMethod, $model, PlaceOrderResponse $response, string $email = ""): PlaceOrderResponse
    {
        $this->appendPaymentMethodId($paymentMethodId, $intent['id']);

        $data = [
            'response_type' => 'confirmation_required',
            'intent_id' => $intent['id'],
            'client_secret' => $intent['clientSecret']
        ];
        if ($this->isRedirectMethodConstant($paymentMethod->getMethod())) {
            $browserInformation = '';
            if (!empty($paymentMethod->getAdditionalData())) {
                $browserInformation = $paymentMethod->getAdditionalData()['browser_information'] ?? "";
            }
            $data['next_action'] = $this->getAirwallexPaymentsNextAction($intent, $paymentMethod, $browserInformation, $model->getBillingAddress(), $email);
        }

        $this->cache->save(1, $this->reCaptchaValidationPlugin->getCacheKey($intent['id']), [], 3600);
        return $response->setData($data);
    }
}
