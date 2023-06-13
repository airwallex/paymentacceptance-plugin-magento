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
namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent as PaymentIntentResource;
use Airwallex\Payments\Model\ResourceModel\PaymentIntent\CollectionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;

class PaymentIntentRepository
{
    private const RACE_SLEEP_TIME = 4;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var PaymentIntentFactory
     */
    private PaymentIntentFactory $paymentIntentFactory;

    /**
     * @var PaymentIntentResource
     */
    private PaymentIntentResource $paymentIntent;

    /**
     * @var OrderInterface
     */
    private OrderInterface $order;

    /**
     * PaymentIntentRepository constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param PaymentIntentFactory $paymentIntentFactory
     * @param PaymentIntentResource $paymentIntent
     * @param OrderInterface $order
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        PaymentIntentFactory $paymentIntentFactory,
        PaymentIntentResource $paymentIntent,
        OrderInterface $order
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->paymentIntentFactory = $paymentIntentFactory;
        $this->paymentIntent = $paymentIntent;
        $this->order = $order;
    }

    /**
     * @param string $paymentIntentId
     *
     * @return OrderInterface|null
     */
    public function getOrder(string $paymentIntentId): ?OrderInterface
    {
        $intentCollection = $this->collectionFactory->create();

        /** @var PaymentIntentInterface|null $intent */
        $intent = $intentCollection
            ->addFieldToFilter('payment_intent_id', ['eq' => $paymentIntentId])
            ->getFirstItem();

        if ($intent->getId() === null) {
            return null;
        }

        $order = $this->order->loadByIncrementId($intent->getOrderIncrementId());

        return $order->getId() === null ? null : $order;
    }

    /**
     * @param string $orderIncrement
     * @param string $paymentIntentId
     *
     * @return void
     * @throws AlreadyExistsException
     */
    public function save(string $orderIncrement, string $paymentIntentId): void
    {
        $paymentIntent = $this->paymentIntentFactory->create();
        $paymentIntent->setPaymentIntentId($paymentIntentId)
            ->setOrderIncrementId($orderIncrement);

        $this->paymentIntent->save($paymentIntent);
    }
}
