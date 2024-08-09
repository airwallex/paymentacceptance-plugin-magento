<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\CreditmemoService;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\App\CacheInterface;

class Refund extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_SUCCESS_NAME = 'refund.accepted';

    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    private CreditmemoService $creditmemoService;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * Refund constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param CacheInterface $cache
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        CacheInterface $cache
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->cache = $cache;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     */
    public function execute(object $data): void
    {
        $paymentIntentId = $data->payment_intent_id;

        /** @var Order $order */
        $order = $this->paymentIntentRepository->getOrder($paymentIntentId);
        if (!$order) {
            throw new WebhookException(__('Can\'t find order'));
        }

        $cacheName = $this->refundCacheName($paymentIntentId);
        if ($this->cache->load($cacheName)) {
            $this->cache->remove($cacheName);
            return;
        }

        $record = $this->paymentIntentRepository->getByIntentId($paymentIntentId);
        $detail = $record->getDetail();
        $detailArray = $detail ? json_decode($detail, true) : [];
        if (!empty($detailArray['refund_ids']) && in_array($data->id, $detailArray['refund_ids'], true)) {
            return;
        }

        if ($order->getState() === Order::STATE_HOLDED && $order->canUnhold()) {
            $order->unhold();
        }

        if (!$order->canCreditmemo()) {
            if ($order->canCancel()) {
                $order->cancel();
                $this->orderRepository->save($order);
            }

            return;
        }

        $this->createCreditMemo($order, $data->amount, $data->reason ?? '');
    }

    /**
     * @param Order $order
     * @param float $refundAmount
     * @param string $reason
     *
     * @return void
     * @throws LocalizedException
     */
    private function createCreditMemo(Order $order, float $refundAmount, string $reason): void
    {
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        if (!$invoice) {
            return;
        }

        $totalNotRefunded = $order->getGrandTotal() - $order->getTotalRefunded();

        $creditMemo = $this->creditmemoFactory->createByOrder($order);
        $creditMemo->setInvoice($invoice);

        if ($totalNotRefunded > $refundAmount) {
            $diff = $totalNotRefunded - $refundAmount;
            $creditMemo->setAdjustmentPositive($diff);
        }

        $baseAmount = $this->getBaseAmount($refundAmount, $order->getBaseToOrderRate(), $order->getGrandTotal(), $order->getBaseGrandTotal());
        $remained = round($order->getBaseTotalPaid() - $order->getBaseTotalRefunded(), 4);

        if ($baseAmount - $remained >= 0.0001) {
            $baseAmount = $remained;
        }

        $creditMemo->setBaseGrandTotal($baseAmount);
        $creditMemo->setGrandTotal($refundAmount);

        $this->creditmemoService->refund($creditMemo, true);
        $order->addCommentToStatusHistory(__('Order refunded through Airwallex.'));
        $this->orderRepository->save($order);
    }
}
