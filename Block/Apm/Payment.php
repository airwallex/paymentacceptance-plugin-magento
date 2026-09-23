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
namespace Airwallex\Payments\Block\Apm;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\RequestInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Config\Source\Mode;
use Airwallex\PayappsPlugin\CommonLibrary\Util\CurrencyHelper;
use Magento\Sales\Model\OrderRepository;
use Airwallex\Payments\CommonLibraryInit;
use Magento\Quote\Api\CartRepositoryInterface;
use Airwallex\Payments\Exception\SigningKeyMissingException;
use Airwallex\Payments\Model\PaymentIntents;
use Airwallex\Payments\Model\ReturnState;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Airwallex\Payments\Helper\ApmElementOptionsHelper;

class Payment extends Template
{
    use HelperTrait;

    protected RequestInterface $request;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected RetrievePaymentIntent $retrievePaymentIntent;
    protected Configuration $configuration;
    protected OrderRepository $orderRepository;
    protected CartRepositoryInterface $quoteRepository;
    protected PaymentIntents $paymentIntents;
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    protected PriceCurrencyInterface $priceCurrency;
    protected ApmElementOptionsHelper $apmElementOptionsHelper;

    /**
     * Constructor
     *
     * @param Context $context
     * @param RequestInterface $request
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param Configuration $configuration
     * @param OrderRepository $orderRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentIntents $paymentIntents
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param ApmElementOptionsHelper $apmElementOptionsHelper
     * @param CommonLibraryInit $commonLibraryInit
     * @param array $data
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        PaymentIntentRepository $paymentIntentRepository,
        RetrievePaymentIntent $retrievePaymentIntent,
        Configuration $configuration,
        OrderRepository $orderRepository,
        CartRepositoryInterface $quoteRepository,
        PaymentIntents $paymentIntents,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        PriceCurrencyInterface $priceCurrency,
        ApmElementOptionsHelper $apmElementOptionsHelper,
        CommonLibraryInit $commonLibraryInit,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->request = $request;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $this->configuration = $configuration;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->paymentIntents = $paymentIntents;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->priceCurrency = $priceCurrency;
        $this->apmElementOptionsHelper = $apmElementOptionsHelper;
        $commonLibraryInit->exec();
    }

    /**
     * Get frontend config for the APM completion page
     *
     * @return array|null
     */
    public function getPaymentConfig()
    {
        try {
            $entityData = $this->loadEntity();
            if (!$entityData) {
                return null;
            }

            ['entity' => $entity, 'type' => $entityType, 'id' => $entityId, 'intent_record' => $paymentIntentRecord] = $entityData;

            if (!$paymentIntentRecord) {
                $this->_logger->error("APM Payment: Payment intent not found for {$entityType} ID: {$entityId}");
                return null;
            }

            $paymentIntent = $this->retrievePaymentIntent
                ->setPaymentIntentId($paymentIntentRecord->getIntentId())
                ->send();

            $orderCurrency = $entityType === 'order'
                ? $entity->getOrderCurrencyCode()
                : $entity->getQuoteCurrencyCode();

            $returnState = $this->generateReturnState(
                $entityType === 'order' ? ReturnState::SCOPE_ORDER : ReturnState::SCOPE_QUOTE,
                (int) $entityId
            );

            $config = [
                'env' => Mode::normalizeApiEnv($this->configuration->getMode()),
                'return_url' => $this->getUrl('airwallex/redirect', [
                    '_query' => ['awx_return_result' => 'success', 'id' => $entityId, 'type' => $entityType,
                        'intent_id' => $paymentIntentRecord->getIntentId(), 'state' => $returnState]
                ]),
                $entityType . '_id' => $entityId,
                'intent_base_currency' => $paymentIntent->getBaseCurrency() ?: $paymentIntent->getCurrency(),
                'intent_base_amount' => $paymentIntent->getBaseCurrency() ? $paymentIntent->getBaseAmount() : $paymentIntent->getAmount(),
                'order_currency' => $orderCurrency,
                'available_currencies' => $this->getAvailableCurrencies(),
                'country_to_currency' => CurrencyHelper::COUNTRY_TO_CURRENCY,
                'currency_to_country' => CurrencyHelper::CURRENCY_TO_COUNTRY_MAP,
                'eu_country_codes' => CurrencyHelper::AVAILABLE_EU_COUNTRY_CODES,
                'elementOptions' => $this->apmElementOptionsHelper->getElementOptions(
                    $paymentIntent,
                    $entity,
                    $entity->getCustomerEmail()
                )
            ];

            $this->_logger->info("APM Payment config generated ({$entityType})");
            return $config;
        } catch (SigningKeyMissingException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->_logger->error('APM Payment: Error generating config - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get order or quote totals for the APM completion page
     *
     * @return array|null
     */
    public function getOrderDetails()
    {
        try {
            $entityData = $this->loadEntity();
            if (!$entityData) {
                return null;
            }

            $entity = $entityData['entity'];
            $isOrder = $entityData['type'] === 'order';

            return [
                'subtotal' => $entity->getSubtotal(),
                'shipping_amount' => $entity->getShippingAmount(),
                'tax_amount' => $entity->getTaxAmount(),
                'discount_amount' => $entity->getDiscountAmount(),
                'grand_total' => $entity->getGrandTotal(),
                'currency_code' => $isOrder ? $entity->getOrderCurrencyCode() : $entity->getQuoteCurrencyCode(),
                'items' => $this->formatItemsForDisplay($entity),
                'shipping_description' => $entity->getShippingDescription()
            ];
        } catch (SigningKeyMissingException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->_logger->error('APM Payment: Error getting order details - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Load the authorized order or quote for the APM completion page
     *
     * @return array|null
     */
    protected function loadEntity()
    {
        $orderId = $this->request->getParam('order_id');
        $quoteId = $this->request->getParam('quote_id');
        $isOrderBeforePayment = $this->configuration->isOrderBeforePayment();

        if ($isOrderBeforePayment) {
            if (!$orderId) {
                $this->_logger->error('APM Payment: Order ID is required');
                return null;
            }
            $order = $this->orderRepository->get($orderId);
            $intentRecord = $this->paymentIntentRepository->getByOrderId($orderId);
            if (!$this->validateOrderAccess($order, (string) $this->request->getParam('state'))) {
                $this->_logger->error("APM Payment: Unauthorized order ID: {$orderId}");
                return null;
            }
            return [
                'entity' => $order,
                'type' => 'order',
                'id' => $orderId,
                'intent_record' => $intentRecord
            ];
        }

        if (!$quoteId) {
            $this->_logger->error('APM Payment: Quote ID is required');
            return null;
        }
        $numericQuoteId = $this->resolveQuoteId($quoteId);
        $quote = $this->quoteRepository->get($numericQuoteId);
        if (!$this->validateQuoteAccess($quote, (string) $this->request->getParam('state'))) {
            $this->_logger->error("APM Payment: Unauthorized quote ID: {$quoteId}");
            return null;
        }
        return [
            'entity' => $quote,
            'type' => 'quote',
            'id' => $numericQuoteId,
            'intent_record' => $this->paymentIntentRepository->getByQuoteId($numericQuoteId)
        ];
    }

    /**
     * Format visible items for the APM completion page
     *
     * @param mixed $object
     * @return array
     */
    protected function formatItemsForDisplay($object)
    {
        $items = [];
        $isOrder = $object instanceof \Magento\Sales\Model\Order;
        foreach ($object->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'qty' => (int)($isOrder ? $item->getQtyOrdered() : $item->getQty()),
                'price' => $item->getPrice(),
                'row_total' => $item->getRowTotal()
            ];
        }
        return $items;
    }

    /**
     * Format a price for display
     *
     * @param mixed $price
     * @return string
     */
    public function formatPrice($price)
    {
        return $this->priceCurrency->format(
            $price,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION
        );
    }
}
