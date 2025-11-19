<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType as StructPaymentMethodType;
use Airwallex\Payments\Api\ServiceInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay\StartPaymentSession as ApplePayStartPaymentSession;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Airwallex\Payments\Model\Ui\ConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Model\ValidationRules\ShippingAddressValidationRule;
use Magento\Quote\Model\ValidationRules\BillingAddressValidationRule;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Magento\Sales\Model\OrderFactory;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Error;

class Service implements ServiceInterface
{
    use HelperTrait;

    protected Configuration $configuration;
    protected CheckoutData $checkoutHelper;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    protected StoreManagerInterface $storeManager;
    protected RequestInterface $request;
    protected ProductRepositoryInterface $productRepository;
    private SerializerInterface $serializer;
    private LocalizedToNormalized $localizedToNormalized;
    private Resolver $localeResolver;
    private CartRepositoryInterface $quoteRepository;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private QuoteIdMaskResourceModel $quoteIdMaskResourceModel;
    private ShipmentEstimationInterface $shipmentEstimation;
    private RegionFactory $regionFactory;
    private ShippingInformationManagementInterface $shippingInformationManagement;
    private ShippingInformationInterfaceFactory $shippingInformationFactory;
    private ConfigProvider $configProvider;
    private ApplePayStartPaymentSession $applePayStartPaymentSession;
    private ShippingAddressValidationRule $shippingAddressValidationRule;
    private BillingAddressValidationRule $billingAddressValidationRule;
    protected AgreementsConfigProvider $agreementsConfigProvider;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected OrderResourceInterface $orderResource;
    protected OrderFactory $orderFactory;
    protected DirectoryList $directoryList;
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    protected CacheInterface $cache;
    protected Manager $cacheManager;
    protected Writer $configWriter;
    protected LoggerInterface $logger;
    protected RetrievePaymentIntent $retrievePaymentIntent;

    /**
     * Index constructor.
     *
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param ProductRepositoryInterface $productRepository
     * @param SerializerInterface $serializer
     * @param LocalizedToNormalized $localizedToNormalized
     * @param Resolver $localeResolver
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param RegionFactory $regionFactory
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param ConfigProvider $configProvider
     * @param ApplePayStartPaymentSession $applePayStartPaymentSession
     * @param ShippingAddressValidationRule $shippingAddressValidationRule
     * @param BillingAddressValidationRule $billingAddressValidationRule
     * @param AgreementsConfigProvider $agreementsConfigProvider
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param OrderResourceInterface $orderResource
     * @param OrderFactory $orderFactory
     * @param DirectoryList $directoryList
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param CacheInterface $cache
     * @param Manager $cacheManager
     * @param Writer $configWriter
     * @param CommonLibraryInit $commonLibraryInit
     * @param LoggerInterface $logger
     * @param RetrievePaymentIntent $retrievePaymentIntent
     */
    public function __construct(
        Configuration                          $configuration,
        CheckoutData                           $checkoutHelper,
        QuoteIdToMaskedQuoteIdInterface        $quoteIdToMaskedQuoteId,
        StoreManagerInterface                  $storeManager,
        RequestInterface                       $request,
        ProductRepositoryInterface             $productRepository,
        SerializerInterface                    $serializer,
        LocalizedToNormalized                  $localizedToNormalized,
        Resolver                               $localeResolver,
        CartRepositoryInterface                $quoteRepository,
        QuoteIdMaskFactory                     $quoteIdMaskFactory,
        QuoteIdMaskResourceModel               $quoteIdMaskResourceModel,
        ShipmentEstimationInterface            $shipmentEstimation,
        RegionFactory                          $regionFactory,
        ShippingInformationManagementInterface $shippingInformationManagement,
        ShippingInformationInterfaceFactory    $shippingInformationFactory,
        ConfigProvider                         $configProvider,
        ApplePayStartPaymentSession            $applePayStartPaymentSession,
        ShippingAddressValidationRule          $shippingAddressValidationRule,
        BillingAddressValidationRule           $billingAddressValidationRule,
        AgreementsConfigProvider               $agreementsConfigProvider,
        PaymentIntentRepository                $paymentIntentRepository,
        OrderResourceInterface                 $orderResource,
        OrderFactory                           $orderFactory,
        DirectoryList                          $directoryList,
        AvailablePaymentMethodsHelper          $availablePaymentMethodsHelper,
        CacheInterface                         $cache,
        Manager                                $cacheManager,
        Writer                                 $configWriter,
        CommonLibraryInit                      $commonLibraryInit,
        LoggerInterface                        $logger,
        RetrievePaymentIntent                  $retrievePaymentIntent
    )
    {
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->serializer = $serializer;
        $this->localizedToNormalized = $localizedToNormalized;
        $this->localeResolver = $localeResolver;
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->regionFactory = $regionFactory;
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->configProvider = $configProvider;
        $this->applePayStartPaymentSession = $applePayStartPaymentSession;
        $this->shippingAddressValidationRule = $shippingAddressValidationRule;
        $this->billingAddressValidationRule = $billingAddressValidationRule;
        $this->agreementsConfigProvider = $agreementsConfigProvider;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderResource = $orderResource;
        $this->orderFactory = $orderFactory;
        $this->directoryList = $directoryList;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->cache = $cache;
        $this->cacheManager = $cacheManager;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $commonLibraryInit->exec();
    }

    /**
     * Get region id
     *
     * @param string $country
     * @param string $region
     * @return string
     */
    public function regionId(string $country, string $region): string
    {
        $regionId = $this->regionFactory->create()->loadByName($region, $country)->getRegionId();
        if (!$regionId) {
            $regionId = $this->regionFactory->create()->loadByCode($region, $country)->getRegionId();
        }
        return $regionId ?? 0;
    }

    /**
     * Get express data when initialize and quote data updated
     *
     * @return string
     * @throws NoSuchEntityException|InputException
     */
    public function expressData(): string
    {
        $res = $this->quoteData();
        $res['settings'] = $this->settings();

        if ($this->request->getParam("is_product_page") === '1') {
            $res['product_is_virtual'] = $this->getProductIsVirtual();
        }

        return json_encode($res);
    }

    /**
     * Get intent
     *
     * @param string $intentId
     * @return string
     * @throws GuzzleException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws JsonException
     */
    public function intent(string $intentId): string
    {
        $data = [
            'paid' => false,
            'is_order_handled_success' => false,
        ];
        try {
            /** @var StructPaymentIntent $paymentIntentFromApi */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $data['paid'] = $paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured();
        } catch (Exception $e) {
            $this->logger->error(__METHOD__ . ': ' . $e->getMessage());
            return json_encode($data);
        }
        if (!$data['paid'] ) {
            return json_encode($data);
        }
        $intentRecord = $this->paymentIntentRepository->getByIntentId($intentId);
        $quote = $this->quoteRepository->get($intentRecord->getQuoteId());
        if ($quote && $quote->getId()) {
            try {
                if ($this->configuration->isOrderBeforePayment()) {
                    $this->changeOrderStatus($paymentIntentFromApi, $intentRecord->getOrderId(), $quote, __METHOD__);
                } else {
                    $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
                }
            } catch (Exception $e) {
                RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onOrderConfirmationError');
                $this->logger->error(__METHOD__ . ': ' . $e->getMessage());
            }
        }

        $intentRecord = $this->paymentIntentRepository->getByIntentId($intentId);
        try {
            $order = $this->getFreshOrder($intentRecord->getOrderId());
        } catch (Exception $e) {
            $this->logger->error(__METHOD__ . ': ' . $e->getMessage());
        }
        if (!empty($order) && $order->getId() && $order->getStatus() !== Order::STATE_PENDING_PAYMENT && !$this->configuration->isOrderBeforePayment()) {
            $this->setCheckoutSuccess($intentRecord->getQuoteId(), $order);
        }
        $data['is_order_handled_success'] = !empty($order) && $order->getStatus() !== Order::STATE_PENDING_PAYMENT;
        return json_encode($data);
    }

    /**
     * Get quote data
     *
     * @return array
     */
    private function quoteData(): array
    {
        $quote = $this->checkoutHelper->getQuote();

        $cartId = $quote->getId() ?? 0;
        try {
            $maskCartId = $this->quoteIdToMaskedQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $maskCartId = '';
        }

        $taxAmount = $quote->isVirtual()
            ? $quote->getBillingAddress()->getTaxAmount()
            : $quote->getShippingAddress()->getTaxAmount();

        return [
            'subtotal' => $quote->getSubtotal() ?? 0,
            'grand_total' => $quote->getGrandTotal() ?? 0,
            'base_grand_total' => $quote->getBaseGrandTotal() ?? 0,
            'base_currency_code' => $quote->getBaseCurrencyCode() ?? '',
            'base_to_quote_rate' => $quote->getBaseToQuoteRate() ?? '',
            'shipping_amount' => $quote->getShippingAddress()->getShippingAmount() ?? 0,
            'tax_amount' => $taxAmount ?: 0,
            'subtotal_with_discount' => $quote->getSubtotalWithDiscount() ?? 0,
            'cart_id' => $cartId,
            'mask_cart_id' => $maskCartId,
            'is_virtual' => $quote->isVirtual(),
            'customer_id' => $quote->getCustomer()->getId(),
            'quote_currency_code' => $quote->getQuoteCurrencyCode(),
            'email' => $quote->getCustomer()->getEmail(),
            'items_qty' => $quote->getItemsQty() ?? 0,
            'billing_address' => $quote->getBillingAddress()->toArray([
                'city',
                'country_id',
                'postcode',
                'region',
                'street',
                'firstname',
                'lastname',
                'email',
            ]),
        ];
    }

    /**
     * Get admin settings
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function settings(): array
    {
        return [
            'mode' => $this->configuration->getMode(),
            'checkout' => $this->configuration->getCheckout(),
            'express_seller_name' => $this->configuration->getExpressSellerName(),
            'is_express_active' => $this->configuration->isExpressActive(),
            'is_express_phone_required' => $this->configuration->isExpressPhoneRequired(),
            'is_express_auto_capture' => $this->configuration->isAutoCapture('express'),
            'express_style' => $this->configuration->getExpressStyle(),
            'express_button_sort' => $this->configuration->getExpressButtonSort(),
            'country_code' => $this->configuration->getCountryCode(),
            'store_code' => $this->storeManager->getStore()->getCode(),
            'display_area' => $this->configuration->expressDisplayArea(),
            'recaptcha_settings' => $this->configProvider->getReCaptchaConfig(),
            'is_recaptcha_enabled' => $this->configProvider->isReCaptchaEnabled(),
            'recaptcha_type' => $this->configuration->recaptchaType(),
            'agreements' => $this->agreementsConfigProvider->getConfig(),
            'allowed_card_networks' => $this->getAllowedCardNetworks(),
        ];
    }

    public function getAllowedCardNetworks()
    {
        try {
            $allowedNetworks = [];
            $paymentMethodTypes = $this->availablePaymentMethodsHelper->getAllPaymentMethodTypes();
            /** @var StructPaymentMethodType $paymentMethodType */
            foreach ($paymentMethodTypes as $paymentMethodType) {
                if (in_array($paymentMethodType->getName(), ['googlepay', 'applepay'], true)) {
                    $allowedNetworks[$paymentMethodType->getName()] = array_column($paymentMethodType->getCardSchemes(), 'name');
                }
            }
        } catch (Exception|Error $exception) {
            $this->logError(__METHOD__ . $exception->getMessage());

        }
        if (empty($allowedNetworks)) {
            $defaultNetworks = ['visa', 'mastercard', 'amex', 'unionpay', 'jcb', 'discover', 'diners', 'maestro'];
            return [
                'applepay' => $defaultNetworks,
                'googlepay' => $defaultNetworks,
            ];
        }
        return $allowedNetworks;
    }

    /**
     * Get product type from product_id from request
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function getProductIsVirtual(): bool
    {
        $product = $this->productRepository->getById(
            $this->request->getParam("product_id"),
            false,
            $this->storeManager->getStore()->getId()
        );
        return $product->isVirtual();
    }

    /**
     * Add product when click pay in product page
     *
     * @return string
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function addToCart(): string
    {
        $params = $this->request->getParams();
        $productId = $params['product'];
        $related = $params['related_product'];

        if (isset($params['qty'])) {
            $this->localizedToNormalized->setOptions(['locale' => $this->localeResolver->getLocale()]);
            $params['qty'] = $this->localizedToNormalized->filter((string)$params['qty']);
        }

        $quote = $this->checkoutHelper->getQuote();

        try {
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);

            $groupedProductIds = [];
            if (!empty($params['super_group']) && is_array($params['super_group'])) {
                $groupedProductSelections = $params['super_group'];
                $groupedProductIds = array_keys($groupedProductSelections);
            }

            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductId() == $productId || in_array($item->getProductId(), $groupedProductIds)) {
                    $this->checkoutHelper->getQuote()->removeItem($item->getId());
                }
            }

            $this->checkoutHelper->getQuote()->addProduct($product, new DataObject($params));

            if (!empty($related)) {
                $productIds = explode(',', $related);
                $this->checkoutHelper->getQuote()->addProductsByIds($productIds);
            }

            $this->quoteRepository->save($quote);

            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            try {
                $maskCartId = $this->quoteIdToMaskedQuoteId->execute($quote->getId());
            } catch (NoSuchEntityException $e) {
                $maskCartId = '';
            }
            if ($maskCartId === '') {
                $quoteIdMask = $this->quoteIdMaskFactory->create();
                $quoteIdMask->setQuoteId($quote->getId());
                $this->quoteIdMaskResourceModel->save($quoteIdMask);
                $maskCartId = $this->quoteIdToMaskedQuoteId->execute($quote->getId());
            }
            return $this->serializer->serialize([
                'cart_id' => $quote->getId(),
                'mask_cart_id' => $maskCartId,
            ]);
        } catch (Exception $e) {
            RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onAddToCartError');
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    /**
     * Post Address to get method and quote data
     *
     * @return string
     * @throws Exception
     */
    public function postAddress(): string
    {
        $countryId = $this->request->getParam('country_id');
        if (!$countryId) {
            return $this->error(__('Country is required.'));
        }
        $city = $this->request->getParam('city');

        $region = $this->request->getParam('region');
        $postcode = $this->request->getParam('postcode');

        $regionId = $this->regionFactory->create()->loadByName($region, $countryId)->getRegionId();
        if (!$regionId) {
            $regionId = $this->regionFactory->create()->loadByCode($region, $countryId)->getRegionId();
        }

        $quote = $this->checkoutHelper->getQuote();

        $cartId = $quote->getId();
        if (!is_numeric($cartId)) {
            $cartId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id')->getQuoteId();
        }

        $address = $quote->getShippingAddress();
        $address->setCountryId($countryId);

        if ($regionId) {
            $address->setRegionId($regionId);
        } else {
            $address->setRegionId(0);
            $address->setRegion($region);
        }

        if (!$city) {
            $city = $region ?: $countryId;
        }
        $address->setCity($city);

        $address->setPostcode($postcode);

        $methods = $this->shipmentEstimation->estimateByExtendedAddress($cartId, $address);

        $res = [];
        if (!$quote->isVirtual()) {
            if (!count($methods)) {
                return $this->error(__('There are no available shipping method found.'));
            }

            $selectedMethod = $methods[0];
            foreach ($methods as $method) {
                if ($method->getMethodCode() === $this->request->getParam('methodId')) {
                    $selectedMethod = $method;
                    break;
                }
            }

            $shippingInformation = $this->shippingInformationFactory->create([
                'data' => [
                    ShippingInformationInterface::SHIPPING_ADDRESS => $address,
                    ShippingInformationInterface::SHIPPING_CARRIER_CODE => $selectedMethod->getCarrierCode(),
                    ShippingInformationInterface::SHIPPING_METHOD_CODE => $selectedMethod->getMethodCode(),
                ],
            ]);
            $this->shippingInformationManagement->saveAddressInformation($cartId, $shippingInformation);

            foreach ($methods as $method) {
                if ($method->getAvailable()) {
                    $res['methods'][] = $this->formatShippingMethod($method);
                }
            }
            $res['selected_method'] = $this->formatShippingMethod($selectedMethod);
        }
        $res['quote_data'] = $this->quoteData();
        $res['region_id'] = $regionId; // we need this because magento internal bug

        return json_encode($res);
    }

    /**
     * Apple pay validate merchant
     *
     * @return string
     * @throws Exception|GuzzleException
     */
    public function validateMerchant(): string
    {
        $validationUrl = $this->request->getParam('validationUrl');
        if (empty($validationUrl)) {
            return $this->error('Validation URL is empty.');
        }

        $initiativeContext = $this->request->getParam('origin');
        if (empty($initiativeContext)) {
            return $this->error('Initiative Context is empty.');
        }

        return $this->applePayStartPaymentSession->setInitiativeParams([
            'validation_url' => $validationUrl,
            'initiative_context' => $initiativeContext,
        ])->send();
    }

    /**
     * Validate addresses before placing order
     *
     * @return string
     * @throws Exception
     */
    public function validateAddresses(): string
    {
        $quote = $this->checkoutHelper->getQuote();
        $errors = $this->shippingAddressValidationRule->validate($quote);
        if ($errors && $errors[0]->getErrors()) {
            return $this->errorAboutAddress($errors, 'Shipping');
        }
        $errors = $this->billingAddressValidationRule->validate($quote);
        if ($errors && $errors[0]->getErrors()) {
            return $this->errorAboutAddress($errors, 'billing');
        }
        return '{"type": "success"}';
    }

    private function errorAboutAddress($errors, $type)
    {
        $error = '';
        if ($errors && $errors[0]->getErrors()) {
            $error = implode(' ', $errors[0]->getErrors());
            if (strstr($error, '"regionId" is required.')) {
                $error = 'Please check the ' . $type . ' address information. Region is invalid.';
            }
        }
        return $this->error(__($error));
    }

    /**
     * Format shipping method
     *
     * @param ShippingMethodInterface $method
     * @return array
     */
    private function formatShippingMethod(ShippingMethodInterface $method): array
    {
        return [
            'carrier_code' => $method->getCarrierCode(),
            'carrier_title' => $method->getCarrierTitle(),
            'amount' => $method->getAmount(),
            'method_code' => $method->getMethodCode(),
            'method_title' => $method->getMethodTitle(),
        ];
    }
}
