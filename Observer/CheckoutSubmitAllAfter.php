<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class CheckoutSubmitAllAfter implements ObserverInterface
{
    use HelperTrait;

    /**
     * @var OrderRepository
     */
    public OrderRepository $orderRepository;

    /**
     * @var Get
     */
    public Get $intentGet;

    public function __construct(
        OrderRepository $orderRepository,
        Get $intentGet
    )
    {
        $this->orderRepository = $orderRepository;
        $this->intentGet = $intentGet;
    }
    /**
     * @param Observer $observer
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        /** @var string $intentId */
        if ($intentId = $order->getPayment()->getAdditionalInformation('intent_id')) {
            $this->addAVSResultToOrder($order, $intentId);
        }
        if ($this->isRedirectMethodConstant($order->getPayment()->getMethod())) {
            $comment = sprintf('Status changed to %s, payment method is %s.', Order::STATE_PENDING_PAYMENT, 
                $order->getPayment()->getMethod());
            $order->addCommentToStatusHistory(__($comment));
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
        }
        $this->orderRepository->save($order);
    }


    private function addAVSResultToOrder(Order $order, string $intentId)
    {
        $histories = $order->getStatusHistories();
        if (!$histories) {
            return;
        }
        $log = $src = '[Verification] ';
        foreach ($histories as $history) {
            $history->getComment();
            if (strstr($history->getComment(), $log)) return;
        }
        try {
            $resp = $this->intentGet->setPaymentIntentId($intentId)->send();
            $respArr = json_decode($resp, true);
            $brand = $respArr['latest_payment_attempt']['payment_method']['card']['brand'] ?? '';
            if ($brand) $brand = ' Card Brand: ' . strtoupper($brand) . '.';
            $last4 = $respArr['latest_payment_attempt']['payment_method']['card']['last4'] ?? '';
            if ($last4) $last4 = ' Card Last Digits: ' . $last4 . '.';
            $avs_check = $respArr['latest_payment_attempt']['authentication_data']['avs_result'] ?? '';
            if ($avs_check) $avs_check = ' AVS Result: ' . $avs_check . '.';
            $cvc_check = $respArr['latest_payment_attempt']['authentication_data']['cvc_result'] ?? '';
            if ($cvc_check) $cvc_check = ' CVC Result: ' . $cvc_check . '.';
            $log .= $brand . $last4 . $avs_check . $cvc_check;
            if ($log === $src) return;
            $order->addCommentToStatusHistory(__($log));
        } catch (\Exception $e) {}
    }
}
