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
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\UrlInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\App\CacheInterface;
use Airwallex\Payments\Helper\IntentHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

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
     * @return ResponseHttp
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws NoSuchEntityException
     */
    public function execute(): ResponseHttp
    {
        $result = $this->request->getParam('awx_return_result');
        $id = $this->request->getParam('id');
        $from = $this->request->getParam('from');
        $type = $this->request->getParam('type');

        if ($from === 'card') {
            if (!is_numeric($id)) {
                $id = ObjectManager::getInstance()->get(MaskedQuoteIdToQuoteIdInterface::class)->execute($id);
            }
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($id);
            $order = $this->paymentIntentRepository->getOrder($paymentIntent->getIntentId());
            $this->setCheckoutSuccess($id, $order);
            return $this->redirect('checkout/onepage/success');
        }

        if (!empty($result) && $result !== 'success') {
            return $this->redirect('checkout');
        }

        if ($type === 'quote') {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($id);
        } else {
            $paymentIntent = $this->paymentIntentRepository->getByOrderId($id);
        }
        $resp = $this->intentGet->setPaymentIntentId($paymentIntent->getIntentId())->send();
        $intentResponse = json_decode($resp, true);
        $isPaidSuccess = in_array($intentResponse['status'], [
            PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
            PaymentIntentInterface::INTENT_STATUS_SUCCEEDED,
        ], true);

        if ($result === 'success' || $isPaidSuccess) {
            $quote = $this->checkoutData->getQuote();
            if ($this->isOrderBeforePayment()) {
                $this->deactivateQuote($quote);
                $order = $this->getFreshOrder($id);
                $this->setCheckoutSuccess($paymentIntent->getQuoteId(), $order);
            } else {
                $intentResponse['status'] = PaymentIntentInterface::INTENT_STATUS_SUCCEEDED;
                $this->placeOrder($quote->getPayment(), $intentResponse, $quote, self::class);
            }

            return $this->redirect('checkout/onepage/success');
        }
        return $this->redirect('checkout');
    }

    public function redirect($url): ResponseHttp
    {
        $redirectUrl = $this->url->getUrl($url);
        $this->response->setRedirect($redirectUrl, 302);
        return $this->response;
    }
}
