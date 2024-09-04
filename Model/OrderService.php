<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\OrderServiceInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Helper\isOrderCreatedHelper;
use Airwallex\Payments\Plugin\ReCaptchaValidationPlugin;
use Exception;
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
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Mobile_Detect;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order\Address as OrderAddress;

class OrderService implements OrderServiceInterface
{
    use HelperTrait;

    protected PaymentConsentsInterface $paymentConsents;
    protected PaymentIntents $paymentIntents;
    protected Configuration $configuration;
    protected CheckoutData $checkoutHelper;
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
    private OrderCollectionFactory $orderCollectionFactory;
    private OrderManagementInterface $orderManagement;
    private IsOrderCreatedHelper $isOrderCreatedHelper;
    private OrderFactory $orderFactory;
    private OrderResourceInterface $orderResource;
    private Confirm $confirm;
    private IntentHelper $intentHelper;

    public function __construct(
        PaymentConsentsInterface                   $paymentConsents,
        PaymentIntents                             $paymentIntents,
        Configuration                              $configuration,
        CheckoutData                               $checkoutHelper,
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
        OrderCollectionFactory                     $orderCollectionFactory,
        OrderManagementInterface                   $orderManagement,
        IsOrderCreatedHelper                       $isOrderCreatedHelper,
        OrderFactory                               $orderFactory,
        OrderResourceInterface                     $orderResource,
        Confirm                                    $confirm,
        IntentHelper                               $intentHelper
    )
    {
        $this->paymentConsents = $paymentConsents;
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
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
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderManagement = $orderManagement;
        $this->isOrderCreatedHelper = $isOrderCreatedHelper;
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->confirm = $confirm;
        $this->intentHelper = $intentHelper;
    }

    /**
     * Guest place order
     *
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Validator\Exception
     */
    public function airwallexGuestPlaceOrder(
        string           $cartId,
        string           $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        return $this->savePaymentOrPlaceOrder($cartId, $paymentMethod, $billingAddress, $intentId, $email, $from);
    }

    /**
     * Place order
     *
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $intentId
     * @param string|null $from
     * @return PlaceOrderResponseInterface
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Validator\Exception
     */
    public function airwallexPlaceOrder(
        string           $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        ?string          $intentId = '',
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        return $this->savePaymentOrPlaceOrder($cartId, $paymentMethod, $billingAddress, $intentId, '', $from);
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
        ?string          $from = ''
    ): PlaceOrderResponseInterface
    {
        /** @var PlaceOrderResponse $response */
        $response = $this->placeOrderResponseFactory->create();
        $quote = $this->checkoutHelper->getQuote();
        $uid = $quote->getCustomer()->getId();

        if (!$intentId) {
            $this->isOrderCreatedHelper->setIsCreated(false);
            return $this->orderThenIntent($quote, $uid, $cartId, $paymentMethod, $billingAddress, $email, $from, $response);
        }

        $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
        $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
        $intentResponse = json_decode($resp, true);

        try {
            $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote, 'OrderService');
        } catch (Exception $e) {
            $message = trim($e->getMessage(), ' .') . '. Order status change failed. Please try again.';
            $this->errorLog->setMessage($message, $e->getTraceAsString(), $intentId)->send();
            return $response->setData([
                'response_type' => 'error',
                'message' => __($message),
            ]);
        }

        if ($this->configuration->isCardVaultActive() && $from === 'card_with_saved') {
            try {
                $this->paymentConsents->syncVault($uid);
            } catch (Exception $e) {
                $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $intentId)->send();
            }
        }

        return $response->setData([
            'response_type' => 'success',
            'order_id' => $paymentIntent->getOrderId()
        ]);
    }

    /**
     * @param Quote $quote
     * @param $uid
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @param string|null $email
     * @param string|null $from
     * @param PlaceOrderResponse $response
     * @return PlaceOrderResponse
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws InputException
     * @throwsJsonException
     * @throws AlreadyExistsException|JsonException
     * @throws LocalizedException
     */
    public function orderThenIntent(Quote $quote, $uid, string $cartId, PaymentInterface $paymentMethod, ?AddressInterface $billingAddress, ?string $email, ?string $from, PlaceOrderResponse $response): PlaceOrderResponse
    {
        $order = $this->getOrderByQuote($quote);
        if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT || !$this->isOrderEqualToQuote($order, $quote, $billingAddress)) {
            if ($uid) {
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
            $order = $this->orderFactory->create();
            $this->orderResource->load($order, $orderId);

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

        $cacheName = AbstractClient::METADATA_PAYMENT_METHOD_PREFIX . $quote->getEntityId();
        $this->cache->save($from ?: $paymentMethod->getMethod(), $cacheName, [], 60);

        $intent = $this->paymentIntents->getIntentByOrder($order);
        $resp = $this->intentGet->setPaymentIntentId($intent['id'])->send();
        $intentResponse = json_decode($resp, true);
        $intentResponse['status'] = PaymentIntentInterface::INTENT_STATUS_SUCCEEDED;
        $this->checkIntentWithOrder($intentResponse, $order);

        $data = [
            'response_type' => 'confirmation_required',
            'intent_id' => $intent['id'],
            'client_secret' => $intent['clientSecret']
        ];
        if ($this->isRedirectMethodConstant($paymentMethod->getMethod())) {
            $data['next_action'] = $this->getAirwallexPaymentsNextAction($order, $intent['id'], $paymentMethod->getMethod());
        }

        $this->cache->save(1, $this->reCaptchaValidationPlugin->getCacheKey($intent['id']), [], 3600);
        return $response->setData($data);
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
    public function getAirwallexPaymentsNextAction(Order $order, string $intentId, $code)
    {
        if (!$intentId) {
            throw new Exception('Intent id is required.');
        }
        $cacheName = $code . '-qrcode-' . $intentId;
        if (!$returnUrl = $this->cache->load($cacheName)) {
            $detect = new Mobile_Detect();
            try {
                $resp = $this->confirm
                    ->setPaymentIntentId($intentId)
                    ->setInformation($this->getPaymentMethodCode($code), $detect->isMobile(), $this->getMobileOS($detect))
                    ->send();
            } catch (Exception $exception) {
                throw new LocalizedException(__($exception->getMessage()));
            }

            $returnUrl = json_encode($resp);
            $this->cache->save($returnUrl, $cacheName, [], 300);
        }
        return $returnUrl;
    }

    /**
     * @param Mobile_Detect $detect
     *
     * @return string|null
     */
    private function getMobileOS(Mobile_Detect $detect): ?string
    {
        if (!$detect->isMobile()) {
            return null;
        }

        return $detect->isAndroidOS() ? 'android' : 'ios';
    }
}
