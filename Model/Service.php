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
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType as StructPaymentMethodType;
use Airwallex\Payments\Api\ServiceInterface;
use Airwallex\Payments\Exception\SigningKeyMissingException;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Model\Config\Source\Mode;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Config\ApplePay\StartPaymentSession as ApplePayStartPaymentSession;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
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
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Error;
use Throwable;

class Service implements ServiceInterface
{
    use HelperTrait {
        conversionQuote as private conversionQuoteResponse;
    }

    private const FX_RATE_LIMIT_MAX = 60;
    private const FX_RATE_LIMIT_WINDOW = 300;

    private CheckoutRequestGuard $checkoutRequestGuard;
    protected Configuration $configuration;
    protected CheckoutData $checkoutHelper;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    protected StoreManagerInterface $storeManager;
    protected RequestInterface $request;
    protected ProductRepositoryInterface $productRepository;
    private StockRegistryInterface $stockRegistry;
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
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param StockRegistryInterface $stockRegistry
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
        RetrievePaymentIntent                  $retrievePaymentIntent,
        CheckoutRequestGuard                   $checkoutRequestGuard,
        StockRegistryInterface                 $stockRegistry
    )
    {
        $this->checkoutRequestGuard = $checkoutRequestGuard;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
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
     * @param string $state Signed return-state token
     * @return string
     * @throws GuzzleException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws JsonException
     */
    public function intent(string $intentId, string $state = ''): string
    {
        $data = [
            'paid' => false,
            'is_order_handled_success' => false,
        ];
        try {
            $intentRecord = $this->paymentIntentRepository->getByIntentId($intentId);
            if (!$this->isIntentRequestAuthorized($intentRecord, $state)) {
                $this->logError(__METHOD__ . ': Payment intent does not belong to the checkout session.');
                return json_encode($data);
            }

            /** @var StructPaymentIntent $paymentIntentFromApi */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send();
            $data['paid'] = $paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured();
        } catch (SigningKeyMissingException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            return json_encode($data);
        }
        if (!$data['paid']) {
            return json_encode($data);
        }

        try {
            $quote = $this->quoteRepository->get((int) $intentRecord->getQuoteId());
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            $quote = null;
        }
        if ($quote && $quote->getId()) {
            try {
                $this->completePaymentIfSuccessful($paymentIntentFromApi, $intentRecord, $quote, __METHOD__);
            } catch (Exception $e) {
                RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onOrderConfirmationError');
                $this->logError(__METHOD__ . ': ' . $e->getMessage());
            }
        }

        $intentRecord = $this->paymentIntentRepository->getByIntentId($intentId);
        try {
            $order = $this->paymentIntentRepository->getOrder($intentId);
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
        }
        $data['is_order_handled_success'] = !empty($order) && $order->getId() && !$order->isCanceled()
            && !in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT], true)
            && $order->getStatus() !== Order::STATE_PENDING_PAYMENT;
        if ($data['is_order_handled_success']) {
            $this->setCheckoutSuccess($intentRecord->getQuoteId(), $order);
            $this->consumeReturnState((int) $intentRecord->getQuoteId(), (int) $order->getId(), $state);
        }
        return json_encode($data);
    }

    /**
     * Confirm the intent belongs to the current checkout session or a valid signed state
     *
     * @param mixed $intentRecord
     * @param string $state
     * @return bool
     */
    private function isIntentRequestAuthorized($intentRecord, string $state): bool
    {
        $quote = $this->checkoutHelper->getQuote();
        if ($quote && $quote->getId()
            && (int) $quote->getId() === (int) $intentRecord->getQuoteId()
            && $this->validateQuoteOwnership($quote)
        ) {
            return true;
        }

        if ((int) $intentRecord->getQuoteId() > 0) {
            try {
                $intentQuote = $this->quoteRepository->get((int) $intentRecord->getQuoteId());
                if ($intentQuote && $intentQuote->getId()) {
                    if ($this->validateQuoteOwnership($intentQuote)) {
                        return true;
                    }
                    if ($this->entityCustomerId($intentQuote) > 0) {
                        return false;
                    }
                }
            } catch (Exception $e) {
                // Quote may no longer exist; fall through to order ownership check.
            }
        }

        if ((int) $intentRecord->getOrderId() > 0) {
            $order = $this->getFreshOrder((int) $intentRecord->getOrderId());
            if ($order && $order->getId()) {
                if ($this->validateOrderOwnership($order)) {
                    return true;
                }
                if ($this->entityCustomerId($order) > 0) {
                    return false;
                }
            }
        }

        return $this->validateIntentStateAccess($intentRecord, $state);
    }

    /**
     * Get quote data
     *
     * @return array
     */
    private function quoteData(): array
    {
        $quote = $this->checkoutHelper->getQuote();
        if (!$quote || ((int) $quote->getId() > 0 && !$this->validateQuoteOwnership($quote))) {
            throw new LocalizedException(__('Checkout session is invalid.'));
        }

        $cartId = $quote->getId() ?? 0;
        try {
            $maskCartId = $cartId ? $this->quoteIdToMaskedQuoteId->execute($cartId) : '';
        } catch (NoSuchEntityException $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
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
            'mode' => Mode::normalizeApiEnv($this->configuration->getMode()),
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
            'currency_to_decimal' => StructPaymentIntent::CURRENCY_TO_DECIMAL,
        ];
    }

    /**
     * Get allowed card networks for express checkout wallets
     *
     * @return array
     */
    public function getAllowedCardNetworks(): array
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
        if (!$this->configuration->isExpressActive()
            || strpos((string) $this->configuration->expressDisplayArea(), 'product_page') === false
        ) {
            throw new CouldNotSaveException(__('Express checkout is not enabled on the product page.'));
        }
        $this->assertExpressRequestOrigin(true);

        $params = $this->request->getParams();
        $productId = $params['product'] ?? null;
        $related = $params['related_product'] ?? '';
        if (empty($productId)) {
            throw new CouldNotSaveException(__('Product is required.'));
        }

        if (isset($params['qty'])) {
            $this->localizedToNormalized->setOptions(['locale' => $this->localeResolver->getLocale()]);
            $params['qty'] = $this->localizedToNormalized->filter((string)$params['qty']);
        }

        $quote = $this->checkoutHelper->getQuote();
        if (!$quote || ((int) $quote->getId() > 0 && !$this->validateQuoteOwnership($quote))) {
            throw new CouldNotSaveException(__('Checkout session is invalid.'));
        }

        try {
            $store = $this->storeManager->getStore();
            $product = $this->productRepository->getById($productId, false, $store->getId());
            $buyRequest = new ExpressProductBuyRequest();
            if (!$buyRequest->isAssignedToWebsite($product->getWebsiteIds(), (int)$store->getWebsiteId())) {
                throw new LocalizedException(
                    __("The product wasn't found. Verify the product and try again.")
                );
            }

            $quote->removeAllItems();
            $result = $quote->addProduct($product, new DataObject($buyRequest->filter($params)));
            if (is_string($result)) {
                throw new LocalizedException(__($result));
            }

            // Express checkout supplies only a partial shipping address (country/region/postcode)
            // until payment is authorized; skip core address validation only while saving the
            // freshly-cleared quote, then immediately restore validation on the quote address.
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShouldIgnoreValidation(true);
            try {
                if (!empty($related)) {
                    foreach (array_filter(array_map('intval', explode(',', $related))) as $relatedId) {
                        $relatedProduct = $this->productRepository->getById($relatedId, false, $store->getId());
                        if (!$buyRequest->isAssignedToWebsite($relatedProduct->getWebsiteIds(), (int)$store->getWebsiteId())) {
                            throw new LocalizedException(__("The product wasn't found. Verify the product and try again."));
                        }
                        if (!$relatedProduct->isVisibleInCatalog()) {
                            continue;
                        }
                        $minimumQty = $this->stockRegistry->getStockItem($relatedId, $store->getWebsiteId())->getMinSaleQty();
                        $result = $quote->addProduct($relatedProduct, $minimumQty > 0 ? $minimumQty : 1);
                        if (is_string($result)) {
                            throw new LocalizedException(__($result));
                        }
                    }
                }

                $this->quoteRepository->save($quote);

                $quote->setTotalsCollectedFlag(false);
                $quote->collectTotals();
                $this->quoteRepository->save($quote);
            } finally {
                $shippingAddress->setShouldIgnoreValidation(false);
            }

            try {
                $maskCartId = $this->quoteIdToMaskedQuoteId->execute($quote->getId());
            } catch (NoSuchEntityException $e) {
                $this->logError(__METHOD__ . $e->getMessage());
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
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onAddToCartError');
            throw new CouldNotSaveException(__($this->sanitizeUserErrorMessage($e, 'Could not add product to cart.')), $e);
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
        if (!$this->configuration->isExpressActive()) {
            return $this->error(__('Express checkout is not enabled.'));
        }
        $this->assertExpressRequestOrigin(true);

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

        $quote = $this->activeCheckoutQuote();

        $cartId = $quote->getId();

        $address = $quote->getShippingAddress();
        $address->setShouldIgnoreValidation(false);
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
            try {
                $this->shippingInformationManagement->saveAddressInformation($cartId, $shippingInformation);
            } finally {
                $address->setShouldIgnoreValidation(false);
                $quote->getShippingAddress()->setShouldIgnoreValidation(false);
            }

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

    private function assertExpressRequestOrigin(bool $requireFormKey = false): void
    {
        if (method_exists($this->request, 'isXmlHttpRequest') && !$this->request->isXmlHttpRequest()) {
            throw new LocalizedException(__('Invalid express checkout request.'));
        }
        if ($requireFormKey) {
            $this->checkoutRequestGuard->assertFormKey($this->request);
        }
        if (!method_exists($this->request, 'getHeader')) {
            return;
        }
        $origin = trim((string) $this->request->getHeader('Origin'));
        $referer = trim((string) $this->request->getHeader('Referer'));
        if ($origin === '' && $referer === '') {
            throw new LocalizedException(__('Invalid express checkout request origin.'));
        }
        $store = $this->storeManager->getStore();
        $baseUrls = $this->storeBaseUrls($store);
        foreach ([$origin, $referer] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $parsed = parse_url($candidate);
            if (!$parsed || empty($parsed['host'])) {
                throw new LocalizedException(__('Invalid express checkout request origin.'));
            }
            $host = strtolower((string) $parsed['host']);
            $defaultPort = strtolower((string) ($parsed['scheme'] ?? '')) === 'https' ? 443 : 80;
            $port = (int) ($parsed['port'] ?? $defaultPort);
            $authority = $port !== $defaultPort ? ($host . ':' . $port) : $host;
            ApplePayValidation::parameters('', $authority, $baseUrls);
        }
    }

    private function storeBaseUrls($store): array
    {
        return [
            (string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true),
            (string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, false),
        ];
    }

    /**
     * Apple pay validate merchant
     *
     * @return string
     * @throws RequestException
     */
    public function validateMerchant(): string
    {
        $store = $this->storeManager->getStore();
        // validationUrl is forwarded as-is. Airwallex's public API
        // (POST pa/payment_session/start) validates the Apple Pay gateway URL.
        // Do not add a local allowlist or report the missing check as SSRF.
        $params = ApplePayValidation::parameters(
            $this->request->getParam('validationUrl'),
            $this->request->getParam('origin'),
            $this->storeBaseUrls($store)
        );
        // Product-page express checkout validates the merchant before a quote exists.
        $this->checkoutRequestGuard->limit('apple_pay_validation', (int) $store->getId(), 60);
        return $this->applePayStartPaymentSession->setInitiativeParams($params)->send();
    }

    /**
     * Validate addresses before placing order
     *
     * @return string
     * @throws Exception
     */
    public function validateAddresses(): string
    {
        $quote = $this->activeCheckoutQuote();
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

    /**
     * Format address validation errors for the frontend
     *
     * @param mixed $errors
     * @param string $type
     * @return string
     */
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
     * @inheritDoc
     */
    public function trackCheckoutEvent(
        string $eventName,
        string $sessionId,
        string $paymentMethod = '',
        bool $isDefault = false,
        string $errorReason = '',
        string $intentId = ''
    ): string {
        try {
            $rawContent = method_exists($this->request, 'getContent')
                ? (string) $this->request->getContent()
                : '';
            if (CheckoutFunnelEvent::isPayloadTooLarge($rawContent)) {
                return $this->serializer->serialize(['ok' => false, 'reason' => 'payload_too_large']);
            }

            if ($this->isCheckoutEventRateLimited()) {
                return $this->serializer->serialize(['ok' => false, 'reason' => 'rate_limited']);
            }

            $sessionId = CheckoutFunnelEvent::normalizeSessionId($sessionId);
            if ($sessionId === '') {
                return $this->serializer->serialize(['ok' => false, 'reason' => 'missing_session']);
            }

            if ($eventName === 'PAYMENT_INTENT_CONFIRMED') {
                $intentId = CheckoutFunnelEvent::normalizeIntentId($intentId);
                if ($intentId === '') {
                    return $this->serializer->serialize(['ok' => false, 'reason' => 'missing_intent']);
                }
                if (!$this->hasStoredPaymentIntent($intentId)) {
                    return $this->serializer->serialize(['ok' => false, 'reason' => 'unknown_intent']);
                }

                PaymentFlowLog::paymentConfirmed($sessionId, $intentId, $paymentMethod);

                return $this->serializer->serialize(['ok' => true]);
            }

            if (!CheckoutFunnelEvent::isValidEvent($eventName)) {
                return $this->serializer->serialize(['ok' => false, 'reason' => 'unknown_event']);
            }

            PaymentFlowLog::log(CheckoutFunnelEvent::buildMessage(
                $eventName,
                $sessionId,
                CheckoutFunnelEvent::funnelPaymentMethod($paymentMethod),
                $isDefault,
                $errorReason,
                ['intent_id' => CheckoutFunnelEvent::normalizeIntentId($intentId)]
            ));

            return $this->serializer->serialize(['ok' => true]);
        } catch (Exception $e) {
            return $this->serializer->serialize(['ok' => false]);
        } catch (Error $e) {
            return $this->serializer->serialize(['ok' => false]);
        }
    }

    public function conversionQuote(string $merchantCurrency, string $shopperCurrency): string
    {
        $this->assertCurrencyCode($merchantCurrency);
        $this->assertCurrencyCode($shopperCurrency);
        $store = $this->storeManager->getStore();
        $this->checkoutRequestGuard->limit(
            'conversion_quote',
            (int) $store->getId(),
            self::FX_RATE_LIMIT_MAX,
            self::FX_RATE_LIMIT_WINDOW
        );
        return $this->conversionQuoteResponse($merchantCurrency, $shopperCurrency);
    }

    private function activeCheckoutQuote()
    {
        $quote = $this->checkoutHelper->getQuote();
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$quote || !(int) $quote->getId() || !$quote->getIsActive()
            || (int) $quote->getItemsCount() < 1
            || (int) $quote->getStoreId() !== $storeId
            || !$this->validateQuoteOwnership($quote)
        ) {
            throw new LocalizedException(__('Checkout session is invalid.'));
        }

        return $quote;
    }

    private function assertCurrencyCode(string $currency): void
    {
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new LocalizedException(__('Currency is invalid.'));
        }
    }

    private function hasStoredPaymentIntent(string $intentId): bool
    {
        try {
            $record = $this->paymentIntentRepository->getByIntentId($intentId);
        } catch (Exception $e) {
            return false;
        }

        return $record && (string) $record->getIntentId() === $intentId;
    }

    /**
     * Fixed-window per-IP rate limit for the anonymous checkout-event endpoint.
     *
     * @return bool
     */
    private function isCheckoutEventRateLimited(): bool
    {
        try {
            $this->checkoutRequestGuard->limit('checkout_event', (int) $this->storeManager->getStore()->getId(),
                CheckoutFunnelEvent::RATE_LIMIT_MAX, CheckoutFunnelEvent::RATE_LIMIT_WINDOW);
            return false;
        } catch (Throwable $e) {
            return true;
        }
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
