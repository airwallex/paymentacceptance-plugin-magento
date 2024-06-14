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
use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use stdClass;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Magento\Sales\Model\OrderRepository;

class Webhook
{
    private const HASH_ALGORITHM = 'sha256';

    public const AUTHORIZED_WEBHOOK_NAMES = ['payment_intent.requires_capture'];

    /**
     * @var Refund
     */
    private Refund $refund;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var Capture
     */
    private Capture $capture;

    /**
     * @var Cancel
     */
    private Cancel $cancel;

    /**
     * @var PaymentIntentRepository
     */    
    protected PaymentIntentRepository $paymentIntentRepository;

    /**
     * @var Get
     */
    public Get $intentGet;

    /**
     * @var OrderRepository
     */
    public OrderRepository $orderRepository;

    /**
     * Webhook constructor.
     *
     * @param Configuration $configuration
     * @param Refund $refund
     * @param Capture $capture
     * @param Cancel $cancel
     */
    public function __construct(
        Configuration $configuration, 
        Refund $refund, 
        Capture $capture, 
        Cancel $cancel,
        PaymentIntentRepository $paymentIntentRepository,
        Get $intentGet,
        OrderRepository $orderRepository
    )
    {
        $this->refund = $refund;
        $this->configuration = $configuration;
        $this->capture = $capture;
        $this->cancel = $cancel;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->intentGet = $intentGet;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param Http $request
     *
     * @return void
     * @throws WebhookException
     */
    public function checkChecksum(Http $request): void
    {
        $signature = $request->getHeader('x-signature');
        $data = $request->getHeader('x-timestamp') . $request->getContent();

        if (hash_hmac(self::HASH_ALGORITHM, $data, $this->configuration->getWebhookSecretKey()) !== $signature) {
            throw new WebhookException(__('failed to verify the signature'));
        }
    }

    /**
     * @param string $type
     * @param stdClass $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     */
    public function dispatch(string $type, stdClass $data): void
    {
        if ($type === Refund::WEBHOOK_SUCCESS_NAME) {
            $this->refund->execute($data);
        }

        if (in_array($type, Capture::WEBHOOK_NAMES)) {
            $this->addAVSResult($data);
            $this->capture->execute($data);
        }

        if (in_array($type, self::AUTHORIZED_WEBHOOK_NAMES)) {
            $this->addAVSResult($data);
        }

        if ($type === Cancel::WEBHOOK_NAME) {
            $this->cancel->execute($data);
        }
    }

    protected function addAVSResult($data)
    {
        $id = $data->payment_intent_id ?? $data->id;
        $order = $this->paymentIntentRepository->getOrder($id);
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
            $resp = $this->intentGet->setPaymentIntentId($id)->send();

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
            $this->orderRepository->save($order);
        } catch (\Exception $e) {}
    }
}
