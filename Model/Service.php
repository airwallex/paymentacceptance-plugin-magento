<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PlaceOrderResponseInterface;
use Airwallex\Payments\Api\Data\PlaceOrderResponseInterfaceFactory;
use Airwallex\Payments\Api\ServiceInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Client\Request\ApplePayValidateMerchant;
use Airwallex\Payments\Plugin\ReCaptchaValidationPlugin;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Airwallex\Payments\Model\Ui\ConfigProvider;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;

class Service implements ServiceInterface
{
    protected PaymentIntents $paymentIntents;
    protected Configuration $configuration;
    protected CheckoutData $checkoutHelper;
    protected GuestPaymentInformationManagementInterface $guestPaymentInformationManagement;
    protected PaymentInformationManagementInterface $paymentInformationManagement;
    protected PlaceOrderResponseInterfaceFactory $placeOrderResponseFactory;
    protected CacheInterface $cache;
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
    private RegionInterfaceFactory $regionInterfaceFactory;
    private RegionFactory $regionFactory;
    private ShippingInformationManagementInterface $shippingInformationManagement;
    private ShippingInformationInterfaceFactory $shippingInformationFactory;
    private ConfigProvider $configProvider;
    private ApplePayValidateMerchant $validateMerchant;

    /**
     * Index constructor.
     *
     * @param PaymentIntents $paymentIntents
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param PlaceOrderResponseInterfaceFactory $placeOrderResponseFactory
     * @param CacheInterface $cache
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
     * @param RegionInterfaceFactory $regionInterfaceFactory
     * @param RegionFactory $regionFactory
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param ConfigProvider $configProvider
     * @param Get $intentGet
     * @param ApplePayValidateMerchant $validateMerchant
     */
    public function __construct(
        PaymentIntents $paymentIntents,
        Configuration $configuration,
        CheckoutData $checkoutHelper,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        PlaceOrderResponseInterfaceFactory $placeOrderResponseFactory,
        CacheInterface $cache,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        ProductRepositoryInterface $productRepository,
        SerializerInterface $serializer,
        LocalizedToNormalized $localizedToNormalized,
        Resolver $localeResolver,
        CartRepositoryInterface $quoteRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel,
        ShipmentEstimationInterface $shipmentEstimation,
        RegionInterfaceFactory $regionInterfaceFactory,
        RegionFactory $regionFactory,
        ShippingInformationManagementInterface $shippingInformationManagement,
        ShippingInformationInterfaceFactory $shippingInformationFactory,
        ConfigProvider $configProvider,
        Get $intentGet,
        ApplePayValidateMerchant $validateMerchant
    ) {
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->placeOrderResponseFactory = $placeOrderResponseFactory;
        $this->cache = $cache;
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
        $this->regionInterfaceFactory = $regionInterfaceFactory;
        $this->regionFactory = $regionFactory;
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->configProvider = $configProvider;
        $this->intentGet = $intentGet;
        $this->validateMerchant = $validateMerchant;
    }
    /**
     * Return URL
     *
     * @return string
     * @throws LocalizedException
     */
    public function redirectUrl(): string
    {
        $checkout = $this->checkoutHelper->getCheckout();

        if (empty($checkout->getLastRealOrderId())) {
            throw new LocalizedException(
                __("Sorry, the order could not be placed. Please contact us for more help.")
            );
        }

        return $checkout->getAirwallexPaymentsRedirectUrl();
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws JsonException
     */
    public function airwallexGuestPlaceOrder(
        string $cartId,
        string $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        string $intentId = null
    ): PlaceOrderResponseInterface {
        /** @var PlaceOrderResponse $response */
        $response = $this->placeOrderResponseFactory->create();
        if ($intentId === null) {
            $intent = $this->paymentIntents->getIntents();
            $this->cache->save(1, ReCaptchaValidationPlugin::getCacheKey($intent['id']), [], 3600);

            $response->setData([
                'response_type' => 'confirmation_required',
                'intent_id' => $intent['id'],
                'client_secret' => $intent['clientSecret']
            ]);
        } else {
            try {
                $this->checkIntent($intentId);
                $orderId = $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $email,
                    $paymentMethod,
                    $billingAddress
                );

                $response->setData([
                    'response_type' => 'success',
                    'order_id' => $orderId
                ]);
            } catch (Exception $e) {
                $this->paymentIntents->removeIntents();
                throw $e;
            }
        }

        return $response;
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws JsonException
     */
    public function airwallexPlaceOrder(
        string $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null,
        string $intentId = null
    ): PlaceOrderResponseInterface {
        /** @var PlaceOrderResponse $response */
        $response = $this->placeOrderResponseFactory->create();

        if ($intentId === null) {
            $intent = $this->paymentIntents->getIntents();
            $this->cache->save(1, ReCaptchaValidationPlugin::getCacheKey($intent['id']), [], 3600);

            $response->setData([
                'response_type' => 'confirmation_required',
                'intent_id' => $intent['id'],
                'client_secret' => $intent['clientSecret']
            ]);
        } else {
            try {
                $this->checkIntent($intentId);
                $orderId = $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                    $cartId,
                    $paymentMethod,
                    $billingAddress
                );

                $response->setData([
                    'response_type' => 'success',
                    'order_id' => $orderId
                ]);
            } catch (Exception $e) {
                $this->paymentIntents->removeIntents();
                throw $e;
            }
        }

        return $response;
    }

    /**
     * Get express data when initialize and quote data updated
     *
     * @return string
     * @throws NoSuchEntityException
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
     */
    private function settings(): array
    {
        return [
            'mode' => $this->configuration->getMode(),
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
            $this->storeManager->getStore()->getId(),
            false
        );
        return $product->isVirtual();
    }

    /**
     * Add product when click pay in product page
     *
     * @return string
     * @throws CouldNotSaveException
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
        $regionName = $this->request->getParam('region');

        $regionId = $this->regionFactory->create()->loadByName($regionName, $countryId)->getRegionId();
        $region = $this->regionInterfaceFactory->create()->setRegion($regionName)->setRegionId($regionId);

        $quote = $this->checkoutHelper->getQuote();

        $cartId = $quote->getId();
        if (!is_numeric($cartId)) {
            $cartId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id')->getQuoteId();
        }

        $address = $quote->getShippingAddress();
        $address->setCountryId($this->request->getParam('country_id'));
        $address->setCity($this->request->getParam('city'));
        $address->setRegion($region->getRegion());
        $address->setPostcode($this->request->getParam('postcode'));
        $methods = $this->shipmentEstimation->estimateByExtendedAddress($cartId, $address);

        $res = [];
        if (!$quote->isVirtual()) {
            if (!count($methods)) {
                throw new Exception(__('There are no available shipping method found.'));
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
                    $res['methods'][]=$this->formatShippingMethod($method);
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
    public function validateMerchant()
    {
        $validationUrl = $this->request->getParam('validationUrl');
        if ( empty( $validationUrl ) ) {
            throw new Exception( 'Validation URL is empty.' );
        }

        $initiativeContext = $this->request->getParam('origin');
        if ( empty( $initiativeContext ) ) {
            throw new Exception( 'Initiative Context is empty.' );
        }

        return $this->validateMerchant->setInitiativeParams([
            'validation_url' => $validationUrl,
            'initiative_context' => $initiativeContext,
        ])->send();
    }

    /**
     * Format shipping method
     *
     * @param $method
     * @return array
     */
    private function formatShippingMethod($method): array
    {
        return [
            'carrier_code' => $method->getCarrierCode(),
            'carrier_title' => $method->getCarrierTitle(),
            'amount' => $method->getAmount(),
            'method_code' => $method->getMethodCode(),
            'method_title' => $method->getMethodTitle(),
        ];
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    protected function checkIntent($id)
    {
        $resp = $this->intentGet->setPaymentIntentId($id)->send();

        $respArr = json_decode($resp, true);
        $okStatus = [$this->intentGet::INTENT_STATUS_SUCCESS, $this->intentGet::INTENT_STATUS_REQUIRES_CAPTURE];
        if (!in_array($respArr['status'], $okStatus, true)) {
            throw new Exception(__('Something went wrong while processing your request. Please try again later.'));
        }
    }
}
