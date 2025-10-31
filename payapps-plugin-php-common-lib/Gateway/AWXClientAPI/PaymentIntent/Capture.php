<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;

class Capture extends AbstractApi
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
        return 'pa/payment_intents/' . $this->id . '/capture';
    }

    /**
     * @param string $paymentIntentId
     *
     * @return Capture
     */
    public function setPaymentIntentId(string $paymentIntentId): Capture
    {
        $this->id = $paymentIntentId;
        return $this;
    }

    /**
     * @param float $amount
     *
     * @return Capture
     */
    public function setAmount(float $amount): Capture
    {
        return $this->setParam('amount', $amount);
    }

    /**
     * @param array $fundsSplitData
     *
     * @return Capture
     */
    public function setFundsSplitData(array $fundsSplitData): Capture
    {
        return $this->setParam('funds_split_data', $fundsSplitData);
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