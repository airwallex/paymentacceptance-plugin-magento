<?php

namespace Airwallex\Payments\Controller\Apm;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\RequestInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Controller\Result\RedirectFactory;
use Airwallex\Payments\CommonLibraryInit;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\Payments\Model\Traits\HelperTrait;

class Index implements HttpGetActionInterface
{
    use HelperTrait;
    protected PageFactory $resultPageFactory;
    protected RequestInterface $request;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected RedirectFactory $redirectFactory;
    protected OrderRepositoryInterface $orderRepository;
    protected CartRepositoryInterface $quoteRepository;
    protected LoggerInterface $logger;
    protected Configuration $configuration;
    protected RetrievePaymentIntent $retrievePaymentIntent;

    public function __construct(
        PageFactory $resultPageFactory,
        RequestInterface $request,
        PaymentIntentRepository $paymentIntentRepository,
        RedirectFactory $redirectFactory,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger,
        Configuration $configuration,
        RetrievePaymentIntent $retrievePaymentIntent,
        CommonLibraryInit $commonLibraryInit
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->redirectFactory = $redirectFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $commonLibraryInit->exec();
    }

    public function execute()
    {
        $quoteId = $this->request->getParam('quote_id');
        $orderId = $this->request->getParam('order_id');
        $isOrderBeforePayment = $this->configuration->isOrderBeforePayment();

        if (($isOrderBeforePayment && !$orderId) || (!$isOrderBeforePayment && !$quoteId)) {
            return $this->redirectToCart();
        }

        try {
            $entityType = $isOrderBeforePayment ? 'order' : 'quote';
            $entityId = $isOrderBeforePayment ? $orderId : $quoteId;

            $paymentData = $this->validateAndGetPaymentData($entityType, $entityId);
            if (!$paymentData) {
                return $this->redirectToCart();
            }

            if ($paymentData['intent']->isAuthorized() || $paymentData['intent']->isCaptured()) {
                $this->logger->info("APM Index: Payment already successful for {$entityType} ID: {$entityId}");
                return $this->redirectToSuccess($entityType, $entityId);
            }
        } catch (\Exception $e) {
            $this->logger->error("APM Index: Error checking payment status: " . $e->getMessage());
            return $this->redirectToCart();
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Complete Your Payment'));
        return $resultPage;
    }

    private function validateAndGetPaymentData(string $entityType, $entityId): ?array
    {
        if ($entityType === 'order') {
            return $this->validateOrderPayment($entityId);
        }
        return $this->validateQuotePayment($entityId);
    }

    private function validateOrderPayment($orderId): ?array
    {
        $paymentIntentRecord = $this->paymentIntentRepository->getByOrderId($orderId);
        if (!$paymentIntentRecord) {
            $this->logger->error("APM Index: Payment intent not found for order ID: {$orderId}");
            return null;
        }

        $order = $this->orderRepository->get($orderId);
        if (!$this->validateOrderOwnership($order)) {
            return null;
        }

        $intentId = $paymentIntentRecord->getIntentId();
        return [
            'record' => $paymentIntentRecord,
            'intent' => $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send()
        ];
    }

    private function validateQuotePayment($quoteId): ?array
    {
        $numericQuoteId = $this->resolveQuoteId($quoteId);
        $paymentIntentRecord = $this->paymentIntentRepository->getByQuoteId($numericQuoteId);

        if (!$paymentIntentRecord) {
            $this->logger->error("APM Index: Payment intent not found for quote ID: {$quoteId}");
            return null;
        }

        $quote = $this->quoteRepository->get($numericQuoteId);
        if (!$this->validateQuoteOwnership($quote)) {
            return null;
        }

        $intentId = $paymentIntentRecord->getIntentId();
        return [
            'record' => $paymentIntentRecord,
            'intent' => $this->retrievePaymentIntent->setPaymentIntentId($intentId)->send()
        ];
    }

    private function redirectToCart()
    {
        return $this->redirectFactory->create()->setPath('checkout/cart');
    }

    private function redirectToSuccess(string $entityType, $entityId)
    {
        return $this->redirectFactory->create()->setPath('airwallex/redirect', [
            'awx_return_result' => 'success',
            'id' => $entityId,
            'type' => $entityType
        ]);
    }
}
