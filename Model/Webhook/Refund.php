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
use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\CreditmemoService;

class Refund extends AbstractWebhook
{
    public const WEBHOOK_SUCCESS_NAME = 'refund.succeeded';

    /**
     * @var CancelHelper
     */
    private CancelHelper $cancelHelper;

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
     * @param CancelHelper $cancelHelper
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CancelHelper $cancelHelper,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->cancelHelper = $cancelHelper;
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
                $this->cancelHelper->setWebhookCanceling(true);
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

        $baseToOrderRate = $order->getBaseToOrderRate();
        $baseTotalNotRefunded = $order->getBaseGrandTotal() - $order->getBaseTotalRefunded();

        $creditMemo = $this->creditmemoFactory->createByOrder($order);
        $creditMemo->setInvoice($invoice);

        if ($baseTotalNotRefunded > $refundAmount) {
            $baseDiff = $baseTotalNotRefunded - $refundAmount;
            $creditMemo->setAdjustmentPositive($baseDiff);
        }

        $creditMemo->setBaseGrandTotal($refundAmount);
        $creditMemo->setGrandTotal($refundAmount * $baseToOrderRate);

        $this->creditmemoService->refund($creditMemo, true);
        $order->addCommentToStatusHistory(__('Order refunded through Airwallex, Reason: %1', $reason));
    }
}
