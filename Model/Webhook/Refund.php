<?php

namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\PaymentIntentRepository;
use GuzzleHttp\Exception\GuzzleException;
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
use JsonException;

class Refund extends AbstractWebhook
{
    use HelperTrait;

    public const WEBHOOK_SUCCESS_NAME = 'refund.accepted';

    /**
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;

    /**
     * @var PaymentIntentRepository
     */
    private PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    private CreditmemoService $creditmemoService;

    /**
     * @var Get
     */
    private Get $intentGet;

    /**
     * Refund constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param CacheInterface $cache
     * @param Get $intentGet
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CacheInterface $cache,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        Get $intentGet
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cache = $cache;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->intentGet = $intentGet;
    }

    /**
     * @param object $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     * @throws JsonException
     */
    public function execute(object $data): void
    {
        $intentId = $data->payment_intent_id;

        /** @var Order $order */
        $order = $this->paymentIntentRepository->getOrder($intentId);
        if (!$order) {
            throw new WebhookException(__('Can\'t find order'));
        }

        $cacheName = $this->refundCacheName($intentId);
        if ($this->cache->load($cacheName)) {
            $this->cache->remove($cacheName);
            return;
        }

        $record = $this->paymentIntentRepository->getByIntentId($intentId);
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

        $this->createCreditMemo($order, $data->amount, $data->currency, $intentId, $data->reason ?? '');
    }

    /**
     * @param Order $order
     * @param float $refundAmount
     * @param string $refundCurrency
     * @param string $intentId
     * @param string $reason
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function createCreditMemo(Order $order, float $refundAmount, string $refundCurrency, string $intentId, string $reason): void
    {
        /** @var Order\Invoice $invoice */
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
        if ($refundCurrency !== $order->getOrderCurrencyCode()) {
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $intentResponse = json_decode($resp, true);
            $refundAmount = round($refundAmount / $intentResponse['amount'] * $order->getBaseGrandTotal(), 2);
            $creditMemo->setBaseGrandTotal($refundAmount);
            $creditMemo->setGrandTotal($this->convertToDisplayCurrency($refundAmount, $order->getBaseToOrderRate(), false));
        }

        $this->creditmemoService->refund($creditMemo, true);
        $this->orderRepository->save($order);
    }
}
