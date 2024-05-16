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
     * Refund constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
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
        $order = $this->paymentIntentRepository->getOrder($data->payment_intent_id);

        if ($order === null) {
            throw new WebhookException(__('Can\'t find order'));
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

        $this->createCreditMemo($order, $data->amount, $data->reason);
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

        if ($invoice === null) {
            return;
        }

        $totalNotRefunded = $order->getGrandTotal() - $order->getTotalRefunded();

        $creditMemo = $this->creditmemoFactory->createByOrder($order);
        $creditMemo->setInvoice($invoice);

        if ($totalNotRefunded > $refundAmount) {
            $diff = $totalNotRefunded - $refundAmount;
            $creditMemo->setAdjustmentPositive($diff);
        }

        $creditMemo->setBaseGrandTotal($this->convertToDisplayCurrency($refundAmount, $order->getBaseToOrderRate(), true));
        $creditMemo->setGrandTotal($refundAmount);

        $this->creditmemoService->refund($creditMemo, true);
        $order->addCommentToStatusHistory(__('Order refunded through Airwallex, Reason: %1', $reason));
        $this->orderRepository->save($order);
    }
}
