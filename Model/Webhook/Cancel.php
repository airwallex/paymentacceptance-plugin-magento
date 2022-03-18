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
use Magento\Sales\Model\OrderRepository;

class Cancel extends AbstractWebhook
{
    public const WEBHOOK_NAME = 'payment_attempt.cancelled';

    /**
     * @var CancelHelper
     */
    private $cancelHelper;

    /**
     * Cancel constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CancelHelper $cancelHelper
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository,
        CancelHelper $cancelHelper
    ) {
        parent::__construct($orderRepository, $paymentIntentRepository);
        $this->cancelHelper = $cancelHelper;
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
        $paymentIntent = $data->payment_intent_id;
        $order = $this->paymentIntentRepository->getOrder($paymentIntent);

        if ($order === null) {
            throw new WebhookException(__('Can\'t find order %s', $paymentIntent));
        }

        if (!$order->canCancel()) {
            throw new WebhookException(__('Can\'t cancel order %s', $paymentIntent));
        }

        $this->cancelHelper->setWebhookCanceling(true);
        $order->cancel();
        $this->orderRepository->save($order);
    }
}
