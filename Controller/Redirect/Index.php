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
use Magento\Quote\Api\CartRepositoryInterface;
use Airwallex\Payments\Exception\SigningKeyMissingException;
use Airwallex\Payments\Model\PaymentIntentRepository;
use Airwallex\Payments\Model\ReturnState;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent\Retrieve as RetrievePaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;

class Index implements HttpGetActionInterface
{
    use HelperTrait;

    public ResponseHttp $response;
    public RequestInterface $request;
    public Data $checkoutData;
    public PaymentIntentRepository $paymentIntentRepository;
    public CartRepositoryInterface $quoteRepository;
    public RetrievePaymentIntent $retrievePaymentIntent;
    private UrlInterface $url;
    private CommonLibraryInit $commonLibraryInit;

    /**
     * Constructor
     *
     * @param ResponseHttp $response
     * @param RequestInterface $request
     * @param Data $checkoutData
     * @param PaymentIntentRepository $paymentIntentRepository
     * @param CartRepositoryInterface $quoteRepository
     * @param RetrievePaymentIntent $retrievePaymentIntent
     * @param UrlInterface $url
     * @param CommonLibraryInit $commonLibraryInit
     */
    public function __construct(
        ResponseHttp $response,
        RequestInterface $request,
        Data $checkoutData,
        PaymentIntentRepository $paymentIntentRepository,
        CartRepositoryInterface $quoteRepository,
        RetrievePaymentIntent $retrievePaymentIntent,
        UrlInterface $url,
        CommonLibraryInit $commonLibraryInit
    ) {
        $this->response = $response;
        $this->request = $request;
        $this->checkoutData = $checkoutData;
        $this->paymentIntentRepository = $paymentIntentRepository;
        $this->quoteRepository = $quoteRepository;
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
        $result = (string) $this->request->getParam('awx_return_result');
        $entityId = $this->request->getParam('id');
        $from = (string) $this->request->getParam('from');
        $entityType = (string) $this->request->getParam('type');
        $state = (string) $this->request->getParam('state');

        if ($from === 'card') {
            return $this->handleCardReturn($entityId);
        }

        if ($result !== '' && $result !== 'success') {
            return $this->redirect('checkout#payment');
        }

        try {
            [$paymentIntent, $quote] = $this->getAuthorizedPaymentContext($entityType, $entityId, $state);
        } catch (SigningKeyMissingException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Invalid payment return context: ' . $e->getMessage());
            return $this->redirect('checkout#payment');
        }

        try {
            $paymentIntentFromApi = $this->retrievePaymentIntent->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            RemoteLog::error("Retrieve Intent ID {$paymentIntent->getIntentId()} failed: " . $e->getMessage(), 'onApiRequestError');
            return $this->redirect('checkout#payment');
        }

        try {
            if ($this->completePaymentIfSuccessful($paymentIntentFromApi, $paymentIntent, $quote, __METHOD__)) {
                return $this->redirect('checkout/onepage/success');
            }
        } catch (Exception $e) {
            RemoteLog::error(__METHOD__ . ': ' . $e->getMessage(), 'onOrderConfirmationError');
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
        }

        $scope = $entityType === ReturnState::SCOPE_ORDER ? ReturnState::SCOPE_ORDER : ReturnState::SCOPE_QUOTE;
        $entityKey = $scope === ReturnState::SCOPE_ORDER ? (int) $entityId : (int) $quote->getId();
        $pollState = $this->generateReturnState($scope, $entityKey);

        $redirectUrl = 'checkout/?from=RedirectIndex&intent_id=' . urlencode($paymentIntent->getIntentId())
            . '&state=' . urlencode($pollState) . '#payment';
        return $this->redirect($redirectUrl);
    }

    
    /**
     * Complete a card payment return when the shopper is still in checkout session
     *
     * @param mixed $entityId
     * @return ResponseHttp
     */
    private function handleCardReturn($entityId): ResponseHttp
    {
        try {
            $quoteId = $this->resolveQuoteId($entityId);
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($quoteId);
            if (!$paymentIntent || !$paymentIntent->getIntentId()) {
                throw new InputException(__('Invalid payment return context.'));
            }

            $order = $this->paymentIntentRepository->getOrder($paymentIntent->getIntentId());
            if (!$order || !$order->getId()
                || (int) $order->getQuoteId() !== $quoteId
                || !$this->validateOrderOwnership($order)
            ) {
                throw new NoSuchEntityException(__('The order does not exist.'));
            }

            $paymentIntentFromApi = $this->retrievePaymentIntent
                ->setPaymentIntentId($paymentIntent->getIntentId())
                ->send();
            $this->checkIntent($paymentIntentFromApi, $order);

            $this->setCheckoutSuccess($quoteId, $order);
            return $this->redirect('checkout/onepage/success');
        } catch (Exception $e) {
            $this->logError('Unable to process card return: ' . $e->getMessage());
            return $this->redirect('checkout#payment');
        }
    }

    /**
     * Load and authorize the payment intent and quote for a return redirect
     *
     * @param string $entityType
     * @param mixed $entityId
     * @param string $state
     * @return array
     */
    private function getAuthorizedPaymentContext(string $entityType, $entityId, string $state): array
    {
        if (!in_array($entityType, ['quote', 'order'], true)) {
            throw new InputException(__('Invalid payment entity type.'));
        }

        if ($entityType === 'quote') {
            $entityId = $this->resolveQuoteId($entityId);
            $quote = $this->quoteRepository->get($entityId);
            if (!$quote || !$quote->getId() || !$this->validateQuoteAccess($quote, $state)) {
                throw new InputException(__('Invalid checkout session.'));
            }

            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($entityId);
        } else {
            if (!is_numeric($entityId)) {
                throw new InputException(__('Invalid order id.'));
            }
            $paymentIntent = $this->paymentIntentRepository->getByOrderId((int) $entityId);
            $order = $this->getFreshOrder((int) $entityId);
            if (!$order || !$order->getId()
                || !$this->validateOrderAccess($order, $state)
            ) {
                throw new InputException(__('Invalid order.'));
            }

            $quote = $this->quoteRepository->get((int) $order->getQuoteId());
        }

        if (!$quote || !$quote->getId()
            || !$paymentIntent || !$paymentIntent->getIntentId()
            || (int) $paymentIntent->getQuoteId() !== (int) $quote->getId()
        ) {
            throw new NoSuchEntityException(__('The payment intent does not exist.'));
        }

        return [$paymentIntent, $quote];
    }

    /**
     * Redirect the shopper to a Magento URL
     *
     * @param string $url
     * @return ResponseHttp
     */
    public function redirect($url): ResponseHttp
    {
        $redirectUrl = $this->url->getUrl($url);
        $this->response->setRedirect(trim($redirectUrl, '/'));
        return $this->response;
    }
}
