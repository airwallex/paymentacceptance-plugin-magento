<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
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
        $entityId = $this->request->getParam('id');
        $from = $this->request->getParam('from');
        $entityType = $this->request->getParam('type');

        if ($from === 'card') {
            $entityId = $this->resolveQuoteId($entityId);
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($entityId);
            $order = $this->paymentIntentRepository->getOrder($paymentIntent->getIntentId());
            $this->setCheckoutSuccess($entityId, $order);
            return $this->redirect('checkout/onepage/success');
        }

        if (!empty($result) && $result !== 'success') {
            return $this->redirect('checkout#payment');
        }

        if ($entityType === 'quote') {
            $entityId = $this->resolveQuoteId($entityId);
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($entityId);
        } else {
            $paymentIntent = $this->paymentIntentRepository->getByOrderId($entityId);
        }

        if (empty($paymentIntent) || empty($paymentIntent->getIntentId())) {
            $this->logError("Payment Intent for quote $entityId doesn't exist.");
            return $this->redirect('checkout#payment');
        }

        try {
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
                $order = $this->getFreshOrder($entityId);
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
