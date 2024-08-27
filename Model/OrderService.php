<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\OrderServiceInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Methods\CardMethod;
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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Magento\Framework\Exception\InputException;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Airwallex\Payments\Model\Methods\ExpressCheckout;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

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
    protected OrderRepositoryInterface $orderRepository;
    public PaymentIntentRepository $paymentIntentRepository;
    public OrderInterface $order;
    private TransactionRepositoryInterface $transactionRepository;

    /**
     * Index constructor.
     *
     * @param PaymentConsentsInterface $paymentConsents
     * @param PaymentIntents $paymentIntents
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param PlaceOrderResponseInterfaceFactory $placeOrderResponseFactory
     * @param CacheInterface $cache
     * @param Get $intentGet
     * @param CartRepositoryInterface $quoteRepository
     * @param ReCaptchaValidationPlugin $reCaptchaValidationPlugin
     * @param ErrorLog $errorLog
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param OrderInterface $order
     * @param TransactionRepositoryInterface $transactionRepository
     */
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
        OrderRepositoryInterface                   $orderRepository,
        PaymentIntentRepository                    $paymentIntentRepository,
        OrderInterface                             $order,
        TransactionRepositoryInterface $transactionRepository
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
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->order = $order;
        $this->transactionRepository = $transactionRepository;
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
     * @throws \Magento\Framework\Validator\Exception
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
            return $this->orderThenIntent($quote, $uid, $cartId, $paymentMethod, $billingAddress, $email, $from, $response);
        }

        $paymentIntent = $this->paymentIntentRepository->getByIntentId($intentId);
        $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
        $intentResponse = json_decode($resp, true);
        /** @var Order $order */
        $order = $this->orderRepository->get($paymentIntent->getOrderId());
        try {
            $this->checkIntentWithOrder($intentResponse, $order);
            $order->setIsInProcess(true);
            $this->orderRepository->save($order);

            $payment = $order->getPayment();
            // todo is here need add the redirect method
            if (($payment->getMethod() === CardMethod::CODE && !$this->configuration->isCardCaptureEnabled())
                || ($payment->getMethod() === ExpressCheckout::CODE && !$this->configuration->isExpressCaptureEnabled())) {
                $this->authorize($order, $intentResponse);
            }

//            $payment->setAmountAuthorized($totalDue);
//            $payment->setBaseAmountAuthorized($baseTotalDue);

                $payment->capture(null);
//            $transaction = $this->transactionRepository->create();
//            $transaction->setTxnId($intentId);
//            $transaction->setOrderId($order->getId());
//            $transaction->setPaymentId($order->getId());
//            $transaction->setTxnType(TransactionInterface::TYPE_AUTH);
//            $transaction->setIsClosed(false);
//            $this->transactionRepository->save($transaction);


            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        } catch (Exception $e) {
            $message = trim($e->getMessage(), ' .') . '. Order status change failed. Please try again.';
            $this->errorLog->setMessage($message, $e->getTraceAsString(), $intentId)->send();
            $response->setData([
                'response_type' => 'error',
                'message' => __($message),
            ]);
            return $response;
        }

        if ($this->configuration->isCardVaultActive() && $from === 'card_with_saved') {
            try {
                $this->paymentConsents->syncVault($uid);
            } catch (Exception $e) {
                $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $intentId)->send();
            }
        }

        $response->setData([
            'response_type' => 'success',
            'order_id' => $order->getId()
        ]);

        return $response;
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
     */
    public function orderThenIntent(Quote $quote, $uid, string $cartId, PaymentInterface $paymentMethod, ?AddressInterface $billingAddress, ?string $email, ?string $from, PlaceOrderResponse $response): PlaceOrderResponse
    {
        $order = $this->getOrderByQuote($quote);
        $orderId = $order->getId();
        if (
            !$orderId
            || !$this->isAmountEqual($order->getGrandTotal(), $quote->getGrandTotal())
            || $order->getOrderCurrencyCode() !== $quote->getQuoteCurrencyCode()
            || $this->paymentIntents->getProductsForCompare($this->getProducts($order)) !== $this->paymentIntents->getProductsForCompare($this->getProducts($quote))
        ) {
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
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
        }

        $cacheName = AbstractClient::METADATA_PAYMENT_METHOD_PREFIX . $quote->getEntityId();
        $this->cache->save($from ?: $paymentMethod->getMethod(), $cacheName, [], 60);

        $intent = $this->paymentIntents->getIntentByOrder($order);

        $resp = $this->intentGet->setPaymentIntentId($intent['id'])->send();
        $intentResponse = json_decode($resp, true);
        $this->checkIntent(
            PaymentIntentInterface::INTENT_STATUS_SUCCEEDED,
            $intentResponse['currency'],
            $order->getOrderCurrencyCode(),
            $intentResponse['merchant_order_id'],
            $order->getIncrementId(),
            floatval($intentResponse['amount']),
            $order->getGrandTotal(),
        );

        $this->cache->save(1, $this->reCaptchaValidationPlugin->getCacheKey($intent['id']), [], 3600);
        $response->setData([
            'response_type' => 'confirmation_required',
            'intent_id' => $intent['id'],
            'client_secret' => $intent['clientSecret']
        ]);
        return $response;
    }
}
