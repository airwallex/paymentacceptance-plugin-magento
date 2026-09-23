<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;

class Capture extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var string
     */
    private $currency;

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
        $this->amount = $amount;
        return $this->setParam('amount', $amount);
    }

    /**
     * @param string $currency
     *
     * @return Capture
     */
    public function setCurrency(string $currency): Capture
    {
        $this->currency = $currency;
        return $this;
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
     * @return void
     * @throws \Exception
     */
    protected function initializePostParams()
    {
        // Format amount according to currency decimal places
        if ($this->amount && $this->currency) {
            $this->setParam('amount', AmountHelper::formatAmount($this->amount, $this->currency));
        }

        parent::initializePostParams();
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