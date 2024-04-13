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
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

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
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
    ) {
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->placeOrderResponseFactory = $placeOrderResponseFactory;
        $this->cache = $cache;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
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
            $maskCartId =  $this->quoteIdToMaskedQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $maskCartId = '';
        }


        return json_encode([
            'subtotal' => $quote->getSubtotal() ?? 0,
            'grand_total' => $quote->getGrandTotal() ?? 0,
            'shipping_amount' => $quote->getShippingAddress()->getShippingAmount() ?? 0,
            'tax_amount' => $quote->getShippingAddress()->getTaxAmount() ?? 0,
            'grand_subtotal_with_discount' => $quote->getSubtotalWithDiscount() ?? 0,
            'cart_id' => $cartId,
            'mask_cart_id' => $maskCartId,
            'is_virtual' => $quote->isVirtual(),
            'customer_id' => $quote->getCustomer()->getId(),
            'quote_currency_code' => $quote->getQuoteCurrencyCode(),
            'settings' => [
                'mode' => $this->configuration->getMode(),
                'express_seller_name' => $this->configuration->getExpressSellerName(),
                'is_express_active' => $this->configuration->isExpressActive(),
                'is_express_phone_required' => $this->configuration->isExpressPhoneRequired(),
                'is_express_capture_enabled' => $this->configuration->isExpressCaptureEnabled(),
                'express_style' => $this->configuration->getExpressStyle(),
                'express_button_sort' => $this->configuration->getExpressButtonSort(),
                'country_code' => $this->configuration->getCountryCode(),
            ]
        ]);
    }
}
