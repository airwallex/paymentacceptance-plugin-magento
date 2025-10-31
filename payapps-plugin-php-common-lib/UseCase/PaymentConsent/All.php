<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentConsent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent\GetList as GetPaymentConsentList;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent;
use Exception;

class All
{
    /**
     * @var string
     */
    private $triggeredBy;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @param string $triggeredBy
     *
     * @return All
     */
    public function setNextTriggeredBy(string $triggeredBy): All
    {
        $this->triggeredBy = $triggeredBy;
        return $this;
    }

    /**
     * @param string $airwallexCustomerId
     *
     * @return All
     */
    public function setCustomerId(string $airwallexCustomerId): All
    {
        $this->customerId = $airwallexCustomerId;
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function get(): array
    {
        $page = 0;
        $all = [];
        $maxPage = 100;
        do {
            $getList = (new GetPaymentConsentList())
                ->setCustomerId($this->customerId)
                ->setNextTriggeredBy($this->triggeredBy)
                ->setPage($page)
                ->setStatus(PaymentConsent::STATUS_VERIFIED)
                ->send();
            /** @var PaymentConsent $paymentConsent */
            foreach ($getList->getItems() as $paymentConsent) {
                $all[] = $paymentConsent;
            }

            $page++;
        } while ($page < $maxPage && $getList->hasMore());
        return $all;
    }
}
