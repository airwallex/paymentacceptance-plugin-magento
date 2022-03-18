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

use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Sales\Model\OrderRepository;

abstract class AbstractWebhook
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PaymentIntentRepository
     */
    protected $paymentIntentRepository;

    /**
     * AbstractWebhook constructor.
     *
     * @param OrderRepository $orderRepository
     * @param PaymentIntentRepository $paymentIntentRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        PaymentIntentRepository $paymentIntentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
    }

    /**
     * @param object $data
     *
     * @return void
     */
    abstract public function execute(object $data): void;
}
