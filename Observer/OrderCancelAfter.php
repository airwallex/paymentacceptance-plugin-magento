<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Helper\CancelHelper;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Airwallex\Payments\Model\Client\Request\Log as ErrorLog;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderCancelAfter implements ObserverInterface
{
    use HelperTrait;

    private Cancel $cancel;
    protected PaymentIntentRepository $paymentIntentRepository;
    protected ErrorLog $errorLog;
    protected CacheInterface $cache;
    protected CancelHelper $cancelHelper;
    protected CartRepositoryInterface $quoteRepository;

    public function __construct(
        PaymentIntentRepository  $paymentIntentRepository,
        Cancel                   $cancel,
        ErrorLog                 $errorLog,
        CacheInterface           $cache,
        CancelHelper             $cancelHelper,
        CartRepositoryInterface   $quoteRepository
    )
    {
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->cancel = $cancel;
        $this->errorLog = $errorLog;
        $this->cache = $cache;
        $this->cancelHelper = $cancelHelper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
    }
}
