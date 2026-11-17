<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Refund;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\GetList as StructGetList;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Refund as StructRefund;

class GetList extends AbstractApi
{
    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/refunds';
    }

    /**
     * @param string $connectedAccountId
     *
     * @return self
     */
    public function setConnectedAccountId(string $connectedAccountId): self
    {
        return $this->setParam('connected_account_id', $connectedAccountId);
    }

    /**
     * @param string $currency
     *
     * @return self
     */
    public function setCurrency(string $currency): self
    {
        return $this->setParam('currency', $currency);
    }

    /**
     * @param string $fromCreatedAt ISO8601 timestamp with timezone.
     *
     * @return self
     */
    public function setFromCreatedAt(string $fromCreatedAt): self
    {
        return $this->setParam('from_created_at', $fromCreatedAt);
    }

    /**
     * @param string $toCreatedAt ISO8601 timestamp with timezone.
     *
     * @return self
     */
    public function setToCreatedAt(string $toCreatedAt): self
    {
        return $this->setParam('to_created_at', $toCreatedAt);
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize Max 1000.
     *
     * @return self
     */
    public function setPage(int $pageNumber = 0, int $pageSize = 10): self
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param string $paymentAttemptId
     *
     * @return self
     */
    public function setPaymentAttemptId(string $paymentAttemptId): self
    {
        return $this->setParam('payment_attempt_id', $paymentAttemptId);
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
     * @param string $status RECEIVED, ACCEPTED, SETTLED, or FAILED.
     *
     * @return self
     */
    public function setStatus(string $status): self
    {
        return $this->setParam('status', $status);
    }

    /**
     * @param $response
     *
     * @return StructGetList
     */
    protected function parseResponse($response): StructGetList
    {
        $items = [];
        $responseArray = json_decode((string) $response->getBody(), true) ?: [];
        if (!empty($responseArray['items'])) {
            foreach ($responseArray['items'] as $item) {
                $items[] = new StructRefund($item);
            }
        }

        return new StructGetList([
            'items' => $items,
            'has_more' => !empty($responseArray['has_more']),
        ]);
    }
}
