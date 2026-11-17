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
     * Caller-supplied idempotency key. When set, it is sent as the request_id
     * so the Airwallex API deduplicates a retried/duplicate refund server-side
     * instead of creating a second refund.
     *
     * @var string|null
     */
    private $requestId = null;

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
     * Set a deterministic idempotency key for this refund. Passing the same
     * request_id for a logically identical refund lets the Airwallex API
     * deduplicate it server-side, preventing a double refund on retry or
     * concurrent submission.
     *
     * @param string $requestId
     *
     * @return self
     */
    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function initializePostParams()
    {
        if ($this->amount !== null && $this->currency) {
            $this->setParam('amount', AmountHelper::formatAmount($this->amount, $this->currency));
        }

        parent::initializePostParams();

        // Override the randomly generated request_id with the caller-supplied
        // idempotency key when one is provided, so duplicate refunds are
        // deduplicated by the Airwallex API.
        if ($this->requestId !== null && $this->requestId !== '') {
            $this->setParam('request_id', $this->requestId);
        }
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
