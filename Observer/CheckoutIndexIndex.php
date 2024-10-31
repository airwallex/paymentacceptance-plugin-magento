<?php

namespace Airwallex\Payments\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Message\ManagerInterface;

class CheckoutIndexIndex implements ObserverInterface
{
    protected ResponseFactory $responseFactory;
    protected UrlInterface $url;
    protected ManagerInterface $messageManager;
    protected RequestInterface $request;

    public function __construct(
        ResponseFactory  $responseFactory,
        UrlInterface     $url,
        ManagerInterface $messageManager,
        RequestInterface $request
    )
    {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->messageManager = $messageManager;
        $this->request = $request;
    }

    public function execute(Observer $observer)
    {
        $result = $this->request->getParam('awx_return_result');
        if ($result !== 'success') {
            return;
        }
        $url = $this->url->getUrl('checkout/onepage/success');
        $this->responseFactory->create()->setRedirect($url)->sendResponse();
    }
}
