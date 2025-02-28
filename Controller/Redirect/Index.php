<?php

namespace Airwallex\Payments\Controller\Redirect;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Checkout\Helper\Data;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\UrlInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\App\CacheInterface;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\Quote\Model\Quote;

class Index implements HttpGetActionInterface
{
    use HelperTrait;

    public ResponseHttp $response;
    public RequestInterface $request;
    public Data $checkoutData;
    public QuoteRepository $quoteRepository;
    public PaymentIntentRepository $paymentIntentRepository;
    public Get $intentGet;
    public CacheInterface $cache;
    public IntentHelper $intentHelper;

    private UrlInterface $url;

    public function __construct(
        ResponseHttp $response,
        RequestInterface $request,
        Data $checkoutData,
        QuoteRepository $quoteRepository,
        PaymentIntentRepository $paymentIntentRepository,
        Get $intentGet,
        CacheInterface $cache,
        IntentHelper $intentHelper,
        UrlInterface $url
    ) {
        $this->response = $response;
        $this->request = $request;
        $this->checkoutData = $checkoutData;
        $this->quoteRepository = $quoteRepository;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->intentGet = $intentGet;
        $this->cache = $cache;
        $this->intentHelper = $intentHelper;
        $this->url = $url;
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException|CouldNotSaveException
     */
    public function execute(): ResponseHttp
    {
        $result = $this->request->getParam('awx_return_result');
        $quoteId = $this->request->getParam('quote_id');
        if ($result !== 'success') {
            return $this->redirect('checkout');
        }
        $quote = $this->checkoutData->getQuote();
        if ($quote && $quote->getId()) {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($quote->getId());
            $resp = $this->intentGet->setPaymentIntentId($paymentIntent->getIntentId())->send();
            $intentResponse = json_decode($resp, true);
            $isPaidSuccess = in_array($intentResponse['status'], [
                PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
                PaymentIntentInterface::INTENT_STATUS_SUCCEEDED,
            ], true);
            if ($this->isOrderBeforePayment()) {
                if (!$isPaidSuccess) {
                    $this->deactivateQuote($quote);
                    return $this->redirect('checkout/onepage/success');
                }
                $this->changeOrderStatus($intentResponse, $paymentIntent->getOrderId(), $quote);
            } else {
                if (!$isPaidSuccess) {
                    $intentResponse['status'] = PaymentIntentInterface::INTENT_STATUS_SUCCEEDED;
                }
                $this->placeOrder($intentResponse, $quote, self::class);
                $this->deactivateQuote($quote);
            }
        }

        if ($quoteId) {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($quoteId);
            $order = $this->paymentIntentRepository->getOrder($paymentIntent->getIntentId());
            $this->setCheckoutSuccess($quoteId, $order);
        }
        return $this->redirect('checkout/onepage/success');
    }

    public function redirect($url): ResponseHttp
    {
        $redirectUrl = $this->url->getUrl($url);
        $this->response->setRedirect($redirectUrl, 302);
        return $this->response;
    }

    public function deactivateQuote(Quote $quote)
    {
        if (!empty($quote) && $quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }
    }
}
