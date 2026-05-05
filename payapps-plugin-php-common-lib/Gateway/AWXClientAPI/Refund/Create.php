<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Refund as StructRefund;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;

class Create extends AbstractApi
{
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
        return 'pa/refunds/create';
    }

    /**
     * @param string $paymentIntentId
     *
     * @return self
     */
    public function setPaymentIntentId(string $paymentIntentId): self
    {
        return $this->setParam('payment_intent_id', $paymentIntentId);
    }

    /**
     * @param float $amount
     *
     * @return self
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this->setParam('amount', $amount);
    }

    /**
     * @param string $currency
     *
     * @return self
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @param string $reason
     *
     * @return self
     */
    public function setReason(string $reason): self
    {
        return $this->setParam('reason', $reason);
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
     * @return StructRefund
     */
    protected function parseResponse($response): StructRefund
    {
        return new StructRefund(json_decode((string)$response->getBody(), true));
    }
}
