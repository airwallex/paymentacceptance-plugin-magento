<?php

namespace Airwallex\Payments\Controller\Redirect;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent as StructPaymentIntent;
use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Checkout\Helper\Data;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;

class Index implements HttpGetActionInterface
{
    use HelperTrait;

    public ResponseHttp $response;
    public RequestInterface $request;
    public Data $checkoutData;
    public PaymentIntentRepository $paymentIntentRepository;
    public RetrievePaymentIntent $retrievePaymentIntent;
    private UrlInterface $url;
    private CommonLibraryInit $commonLibraryInit;

    public function __construct(
        ResponseHttp $response,
        RequestInterface $request,
        Data $checkoutData,
        PaymentIntentRepository $paymentIntentRepository,
        RetrievePaymentIntent $retrievePaymentIntent,
        UrlInterface $url,
        CommonLibraryInit $commonLibraryInit
    ) {
        $this->response = $response;
        $this->request = $request;
        $this->checkoutData = $checkoutData;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->retrievePaymentIntent = $retrievePaymentIntent;
        $this->url = $url;
        $commonLibraryInit->exec();
    }

    /**
     * @return ResponseHttp
     * @throws GuzzleException
     * @throws InputException
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
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
            return $this->redirect('checkout#payment');
        }

        if ($type === 'quote') {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($id);
        } else {
            $paymentIntent = $this->paymentIntentRepository->getByOrderId($id);
        }
        /** @var PaymentIntentInterface $paymentIntent */
        if (empty($paymentIntent) || empty($paymentIntent->getIntentId())) {
            $this->logError("Payment Intent for quote $id doesn't exist.");
            return $this->redirect('checkout#payment');
        }
        try {
            /** @var StructPaymentIntent $paymentIntentFromApi */
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            RemoteLog::error("Retrieve Intent ID {$paymentIntent->getIntentId()} failed: " . $e->getMessage(), 'onApiRequestError');
            return $this->redirect('checkout#payment');
        }
        $quote = $this->checkoutData->getQuote();
        if (empty($quote) || empty($quote->getId())) {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($paymentIntent->getQuoteId());
            $order = $this->paymentIntentRepository->getOrder($paymentIntent->getIntentId());
            $this->setCheckoutSuccess($paymentIntent->getQuoteId(), $order);
            return $this->redirect('checkout/onepage/success');
        }
        if ($paymentIntentFromApi->isAuthorized() || $paymentIntentFromApi->isCaptured()) {
            if ($this->isOrderBeforePayment()) {
                $this->changeOrderStatus($paymentIntentFromApi, $paymentIntent->getOrderId(), $quote, __METHOD__);
            } else {
                $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
            }
            return $this->redirect('checkout/onepage/success');
        }
        if ($result === 'success') {
            if ($this->isOrderBeforePayment()) {
                $this->deactivateQuote($quote);
                $order = $this->getFreshOrder($id);
            } else {
                $paymentIntentFromApi->setStatus(PaymentIntentInterface::INTENT_STATUS_SUCCEEDED);
                $this->placeOrder($quote->getPayment(), $paymentIntentFromApi, $quote, __METHOD__);
                $intentRecord = $this->paymentIntentRepository->getByIntentId($paymentIntent->getIntentId());
                $order = $this->getFreshOrder($intentRecord->getOrderId());
            }
            $this->setCheckoutSuccess($paymentIntent->getQuoteId(), $order);
            return $this->redirect('checkout/onepage/success');
        }
        $redirectUrl = $result ? 'checkout#payment' : 'checkout/?from=RedirectIndex&intent_id=' . $paymentIntent->getIntentId() . '#payment';
        return $this->redirect($redirectUrl);
    }

    public function redirect($url): ResponseHttp
    {
        $redirectUrl = $this->url->getUrl($url);
        $this->response->setRedirect(trim($redirectUrl, '/'));
        return $this->response;
    }
}
