<?php

namespace Airwallex\Payments\Controller\Redirect;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Checkout\Helper\Data;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\UrlInterface;

class Index implements HttpGetActionInterface
{
    public ResponseHttp $response;
    public RequestInterface $request;
    public Data $checkoutData;
    public QuoteRepository $quoteRepository;
    private UrlInterface $url;

    public function __construct(
        ResponseHttp $response,
        RequestInterface $request,
        Data $checkoutData,
        QuoteRepository $quoteRepository,
        UrlInterface $url
    ) {
        $this->response = $response;
        $this->request = $request;
        $this->checkoutData = $checkoutData;
        $this->quoteRepository = $quoteRepository;
        $this->url = $url;
    }

    public function execute(): ResponseHttp
    {
        $result = $this->request->getParam('awx_return_result');
        if ($result !== 'success') {
            return $this->redirect('checkout');
        }
        $quote = $this->checkoutData->getQuote();
        if ($quote && $quote->getId() && $quote->getIsActive()) {
            $quote->setIsActive(false);
            $this->quoteRepository->save($quote);
        }
        return $this->redirect('checkout/onepage/success');
    }

    public function redirect($url): ResponseHttp
    {
        $redirectUrl = $this->url->getUrl($url);
        $this->response->setRedirect($redirectUrl, 302);
        return $this->response;
    }
}
