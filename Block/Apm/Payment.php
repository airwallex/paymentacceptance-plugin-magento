<?php

namespace Airwallex\Payments\Block\Apm;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\RequestInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\Payments\Helper\Configuration;
use Magento\Sales\Model\OrderRepository;
use Airwallex\Payments\CommonLibraryInit;
use Magento\Quote\Api\CartRepositoryInterface;
use Airwallex\Payments\Model\PaymentIntents;
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

            $config = [
                'env' => $this->configuration->getMode(),
                'return_url' => $this->getUrl('airwallex/redirect', [
                    '_query' => ['awx_return_result' => 'success', 'id' => $entityId, 'type' => $entityType]
                ]),
                $entityType . '_id' => $entityId,
                'elementOptions' => $this->apmElementOptionsHelper->getElementOptions(
                    $paymentIntent,
                    $entity,
                    $entity->getCustomerEmail()
                )
            ];

            $this->_logger->info("APM Payment config generated ({$entityType})");
            return $config;
        } catch (\Exception $e) {
            $this->_logger->error('APM Payment: Error generating config - ' . $e->getMessage());
            return null;
        }
    }

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
        } catch (\Exception $e) {
            $this->_logger->error('APM Payment: Error getting order details - ' . $e->getMessage());
            return null;
        }
    }

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
            return [
                'entity' => $this->orderRepository->get($orderId),
                'type' => 'order',
                'id' => $orderId,
                'intent_record' => $this->paymentIntentRepository->getByOrderId($orderId)
            ];
        }

        if (!$quoteId) {
            $this->_logger->error('APM Payment: Quote ID is required');
            return null;
        }
        $numericQuoteId = $this->resolveQuoteId($quoteId);
        return [
            'entity' => $this->quoteRepository->get($numericQuoteId),
            'type' => 'quote',
            'id' => $numericQuoteId,
            'intent_record' => $this->paymentIntentRepository->getByQuoteId($numericQuoteId)
        ];
    }

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

    public function formatPrice($price)
    {
        return $this->priceCurrency->format(
            $price,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION
        );
    }
}
