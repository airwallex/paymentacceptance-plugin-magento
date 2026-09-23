<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class Cancel extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/' . $this->id . '/cancel';
    }

    /**
     * @param string $paymentIntentId
     *
     * @return Cancel
     */
    public function setPaymentIntentId(string $paymentIntentId): Cancel
    {
        $this->id = $paymentIntentId;
        return $this;
    }

    /**
     * @param string $cancellationReason
     *
     * @return Cancel
     */
    public function setCancellationReason(string $cancellationReason): Cancel
    {
        return $this->setParam('cancellation_reason', $cancellationReason);
    }

    /**
     * @param $response
     *
     * @return PaymentIntent
     */
    protected function parseResponse($response): PaymentIntent
    {
        return new PaymentIntent(json_decode((string)$response->getBody(), true));
    }
}