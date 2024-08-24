<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Magento\Sales\Model\Order;

class OrderCancelAfter implements ObserverInterface
{
    use HelperTrait;

    private Cancel $cancel;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected ErrorLog $errorLog;
    protected CacheInterface $cache;
    protected CancelHelper $cancelHelper;

    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        Cancel                   $cancel,
        OrderRepositoryInterface $orderRepository,
        ErrorLog                 $errorLog,
        CacheInterface           $cache,
        CancelHelper             $cancelHelper
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->orderRepository = $orderRepository;
        $this->cancel = $cancel;
        $this->errorLog = $errorLog;
        $this->cache = $cache;
        $this->cancelHelper = $cancelHelper;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $method = $order->getPayment()->getMethod();
        if (strpos($method, 'airwallex') !== 0) return;

        if ($this->cancelHelper->isWebhookCanceling()) return;

        $record = $this->paymentIntentRepository->getByOrderId($order->getId());
        $this->cache->save(true, $this->cancelCacheName($record->getIntentId()), [], 3600);
        try {
            $this->cancel->setPaymentIntentId($record->getIntentId())->send();
            $this->addComment($order, 'Order canceled online.');
        } catch (Exception $e) {
            $this->errorLog->setMessage($e->getMessage(), $e->getTraceAsString(), $order->getIncrementId())->send();
            throw new Exception($e->getMessage());
        }
    }
}
