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
use Airwallex\Payments\Model\Methods\CardMethod;
use Airwallex\Payments\Plugin\ReCaptchaValidationPlugin;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\Resolver;
use Magento\Quote\Model\QuoteIdMaskFactory;

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

    private LocalizedToNormalized $localizedToNormalized;
    private Resolver $localeResolver;
    private CartRepositoryInterface $quoteRepository;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private QuoteIdMaskResourceModel $quoteIdMaskResourceModel;

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
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel
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
     * Checks if payment should be captured on order placement
     *
     * @param string $method
     *
     * @return array
     */
    private function getExtraConfiguration(string $method): array
    {
        $data = [];

        if ($method === CardMethod::CODE) {
            $paymentAction = $this->configuration->getCardPaymentAction();
            $data['card']['auto_capture'] = $paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
        }

        return $data;
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
            $orderId = $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder(
                $cartId,
                $paymentMethod,
                $billingAddress
            );

            $response->setData([
                'response_type' => 'success',
                'order_id' => $orderId
            ]);
        }

        return $response;
    }

    /**
     * @return string
     */
    public function getQuote()
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

        $res = [
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
            'settings' => [
                'mode' => $this->configuration->getMode(),
                'express_seller_name' => $this->configuration->getExpressSellerName(),
                'is_express_active' => $this->configuration->isExpressActive(),
                'is_express_phone_required' => $this->configuration->isExpressPhoneRequired(),
                'is_express_capture_enabled' => $this->configuration->isExpressCaptureEnabled(),
                'express_style' => $this->configuration->getExpressStyle(),
                'express_button_sort' => $this->configuration->getExpressButtonSort(),
                'country_code' => $this->configuration->getCountryCode(),
                'store_code' => $this->storeManager->getStore()->getCode(),
            ]
        ];

        if ($this->request->getParam("is_product_page") === '1') {
            $res['product_type'] = $this->getProductType();
        }

        return json_encode($res);
    }

    private function getProductType()
    {
        $product = $this->productRepository->getById(
            $this->request->getParam("product_id"),
            false,
            $this->storeManager->getStore()->getId(),
            false
        );
        return $product->getTypeId();
    }

    /**
     * @return string
     */
    public function addToCart()
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
            // Get Product
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);

            $groupedProductIds = [];
            if (!empty($params['super_group']) && is_array($params['super_group'])) {
                $groupedProductSelections = $params['super_group'];
                $groupedProductIds = array_keys($groupedProductSelections);
            }

            // Check is update required
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductId() == $productId || in_array($item->getProductId(), $groupedProductIds)) {
                    $item = $this->checkoutHelper->getQuote()->removeItem($item->getId());
                }
            }

            // Add Product to Cart
            $item = $this->checkoutHelper->getQuote()->addProduct($product, new DataObject($params));

            if (!empty($related)) {
                $productIds = explode(',', $related);
                $this->checkoutHelper->getQuote()->addProductsByIds($productIds);
            }

            $this->quoteRepository->save($quote);

            // Update totals
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
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }
}
