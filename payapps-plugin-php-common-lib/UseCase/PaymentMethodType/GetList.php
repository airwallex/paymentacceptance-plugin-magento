<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentMethodType;

use Airwallex\PayappsPlugin\CommonLibrary\Cache\CacheTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentMethodType\GetList as GetPaymentMethodTypeList;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType;
use Exception;

class GetList
{
    use CacheTrait;

    /**
     * @var null|bool
     */
    private $active;

    /**
     * @var bool
     */
    private $includeResources;

    /**
     * @var string
     */
    private $countryCode;

    /**
     * @var string
     */
    private $transactionMode;

    /**
     * @var string
     */
    private $transactionCurrency;

    /**
     * @var int
     */
    private $cacheTime = 3600;

    /**
     * @param int $cacheTime
     *
     * @return GetList
     */
    public function setCacheTime(int $cacheTime): GetList
    {
        $this->cacheTime = $cacheTime;
        return $this;
    }

    /**
     * @param bool|null $active
     *
     * @return GetList
     */
    public function setActive(bool $active): GetList
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return null|bool
     */
    public function getActive()
    {
        if ($this->active === null) {
            return null;
        }
        return $this->active;
    }

    /**
     * @param bool $includeResources
     *
     * @return GetList
     */
    public function setIncludeResources(bool $includeResources): GetList
    {
        $this->includeResources = $includeResources;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeResources(): bool
    {
        return $this->includeResources ?? false;
    }

    /**
     * @param string $countryCode
     *
     * @return GetList
     */
    public function setCountryCode(string $countryCode): GetList
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->countryCode ?? '';
    }

    /**
     * @param string $transactionMode
     *
     * @return GetList
     */
    public function setTransactionMode(string $transactionMode): GetList
    {
        $this->transactionMode = $transactionMode;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionMode(): string
    {
        return $this->transactionMode ?? '';
    }

    /**
     * @param string $transactionCurrency
     *
     * @return GetList
     */
    public function setTransactionCurrency(string $transactionCurrency): GetList
    {
        $this->transactionCurrency = $transactionCurrency;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionCurrency(): string
    {
        return $this->transactionCurrency ?? '';
    }

    /**
     * @return array
     * @throws Exception
     */
    public function get(): array
    {
        $cacheName = 'awx_payment_method_types'
            . '_' . $this->getCountryCode()
            . '_' . $this->getTransactionMode()
            . '_' . $this->getTransactionCurrency()
            . '_' . ($this->getincludeResources() ? 'with_resources' : 'without_resources')
            . '_' . ($this->getActive() === null ? '' : ($this->getActive() ? 'active' : 'inactive'));
        return $this->cacheRemember(
            $cacheName,
            function () {
                return $this->getPaymentMethodTypes();
            },
            $this->cacheTime
        );
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPaymentMethodTypes(): array
    {
        $maxPage = 100;
        $page = 0;
        $paymentMethodTypeList = [];
        do {
            $request = new GetPaymentMethodTypeList();
            if ($this->active !== null) {
                $request->setActive($this->active);
            }
            if ($this->includeResources) {
                $request->setIncludeResources($this->includeResources);
            }
            if ($this->countryCode) {
                $request->setCountryCode($this->countryCode);
            }
            if ($this->transactionMode) {
                $request->setTransactionMode($this->transactionMode);
            }
            if ($this->transactionCurrency) {
                $request->setTransactionCurrency($this->transactionCurrency);
            }
            $getList = $request->setPage($page)->send();

            /** @var PaymentMethodType $paymentMethodType */
            foreach ($getList->getItems() as $paymentMethodType) {
                $paymentMethodTypeList[] = $paymentMethodType;
            }

            $page++;
        } while ($page < $maxPage && $getList->hasMore());
        return $paymentMethodTypeList;
    }
}
