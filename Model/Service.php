<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\ServiceInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Client\Request\ApplePayValidateMerchant;
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Airwallex\Payments\Model\Ui\ConfigProvider;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Model\ValidationRules\ShippingAddressValidationRule;
use Magento\Quote\Model\ValidationRules\BillingAddressValidationRule;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Confirm;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use Mobile_Detect;
use Magento\Sales\Model\Spi\OrderResourceInterface;

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
    protected Get $intentGet;
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
    private ApplePayValidateMerchant $validateMerchant;
    private ShippingAddressValidationRule $shippingAddressValidationRule;
    private BillingAddressValidationRule $billingAddressValidationRule;
    protected ErrorLog $errorLog;
    protected AgreementsConfigProvider $agreementsConfigProvider;
    protected Confirm $confirm;
    protected OrderResourceInterface $orderResource;

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
     * @param Get $intentGet
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
     * @param ApplePayValidateMerchant $validateMerchant
     * @param ShippingAddressValidationRule $shippingAddressValidationRule
     * @param BillingAddressValidationRule $billingAddressValidationRule
     * @param ErrorLog $errorLog
     * @param AgreementsConfigProvider $agreementsConfigProvider
     * @param Confirm $confirm
     * @param OrderResourceInterface $orderResource
     */
    public function __construct(
        Configuration                          $configuration,
        CheckoutData                           $checkoutHelper,
        QuoteIdToMaskedQuoteIdInterface        $quoteIdToMaskedQuoteId,
        StoreManagerInterface                  $storeManager,
        RequestInterface                       $request,
        ProductRepositoryInterface             $productRepository,
        SerializerInterface                    $serializer,
        Get                                    $intentGet,
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
        ApplePayValidateMerchant               $validateMerchant,
        ShippingAddressValidationRule          $shippingAddressValidationRule,
        BillingAddressValidationRule           $billingAddressValidationRule,
        ErrorLog                               $errorLog,
        AgreementsConfigProvider               $agreementsConfigProvider,
        Confirm                                $confirm,
        OrderResourceInterface                 $orderResource
    )
    {
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->serializer = $serializer;
        $this->intentGet = $intentGet;
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
        $this->validateMerchant = $validateMerchant;
        $this->shippingAddressValidationRule = $shippingAddressValidationRule;
        $this->billingAddressValidationRule = $billingAddressValidationRule;
        $this->errorLog = $errorLog;
        $this->agreementsConfigProvider = $agreementsConfigProvider;
        $this->confirm = $confirm;
        $this->orderResource = $orderResource;
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
     */
    public function intent(string $intentId): string
    {
        $paid = false;
        try {
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $respArr = json_decode($resp, true);
            $paidStatus = [
                PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
                PaymentIntentInterface::INTENT_STATUS_SUCCEEDED,
            ];
            $paid = in_array($respArr['status'], $paidStatus, true);
        } catch (Exception $e) {
        }
        return json_encode(compact('paid'));
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
            'is_express_capture_enabled' => $this->configuration->isExpressCaptureEnabled(),
            'express_style' => $this->configuration->getExpressStyle(),
            'express_button_sort' => $this->configuration->getExpressButtonSort(),
            'country_code' => $this->configuration->getCountryCode(),
            'store_code' => $this->storeManager->getStore()->getCode(),
            'display_area' => $this->configuration->expressDisplayArea(),
            'recaptcha_settings' => $this->configProvider->getReCaptchaConfig(),
            'is_recaptcha_enabled' => $this->configProvider->isReCaptchaEnabled(),
            'agreements' => $this->agreementsConfigProvider->getConfig(),
        ];
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
            $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString())->send();
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

        return $this->validateMerchant->setInitiativeParams([
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
