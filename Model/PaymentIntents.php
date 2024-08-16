<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Admin\Cards\Api\CompanyConsentsInterface;
use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Logger\Logger;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Create;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use JsonException;

class PaymentIntents
{
    use HelperTrait;

    protected PaymentConsentsInterface $paymentConsents;
    private Create $paymentIntentsCreate;
    private Get $paymentIntentsGet;
    private Cancel $paymentIntentsCancel;
    private Session $checkoutSession;
    private QuoteRepository $quoteRepository;
    private Logger $logger;
    private UrlInterface $urlInterface;
    private PaymentIntentRepository $paymentIntentRepository;

    public function __construct(
        PaymentConsentsInterface $paymentConsents,
        Create                   $paymentIntentsCreate,
        Get                      $paymentIntentsGet,
        Cancel                   $paymentIntentsCancel,
        Session                  $checkoutSession,
        QuoteRepository          $quoteRepository,
        Logger                   $logger,
        UrlInterface             $urlInterface,
        PaymentIntentRepository  $paymentIntentRepository
    )
    {
        $this->paymentConsents = $paymentConsents;
        $this->paymentIntentsCreate = $paymentIntentsCreate;
        $this->paymentIntentsGet = $paymentIntentsGet;
        $this->paymentIntentsCancel = $paymentIntentsCancel;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->urlInterface = $urlInterface;
        $this->paymentIntentRepository = $paymentIntentRepository;
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws JsonException
     */
    public function createIntent(): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$orderId = $quote->getReservedOrderId()) {
            $quote->reserveOrderId();
            $this->quoteRepository->save($quote);
            $orderId = $quote->getReservedOrderId();
        }

        $uid = $quote->getCustomer()->getId() ?: 0;
        if (interface_exists(CompanyConsentsInterface::class)) {
            $uid = ObjectManager::getInstance()->get(CompanyConsentsInterface::class)->getSuperId($uid);
        }
        $airwallexCustomerId = $this->paymentConsents->getAirwallexCustomerIdInDB($uid);

        $intent = $this->paymentIntentsCreate
            ->setQuote($quote, $this->urlInterface->getUrl('checkout/onepage/success'))
            ->setAirwallexCustomerId($airwallexCustomerId)
            ->send();

        $products = $this->paymentIntentsCreate->getQuoteProducts($quote);
        $shipping = $this->paymentIntentsCreate->getShippingAddress($quote);
        $billing = $this->paymentIntentsCreate->getBillingAddress($quote);
        $this->paymentIntentRepository->save(
            $orderId,
            $intent['id'],
            $quote->getQuoteCurrencyCode(),
            $quote->getGrandTotal(),
            $quote->getId(),
            $quote->getStore()->getId(),
            json_encode(compact('products', 'shipping', 'billing'))
        );

        return $intent;
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws JsonException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws InputException
     */
    public function getIntent(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId()) {
            throw new NoSuchEntityException(__('No cart found.'));
        }
        $paymentIntent = $this->paymentIntentRepository->getByQuoteId($quote->getId());
        if ($paymentIntent && $this->isQuoteEqual($quote, $paymentIntent)) {
            $resp = $this->paymentIntentsGet->setPaymentIntentId($paymentIntent->getPaymentIntentId())->send();
            $respArr = json_decode($resp, true);
            return [
                'clientSecret' => $respArr['client_secret'],
                'id' => $respArr['id'],
            ];
        }
        return $this->createIntent();
    }

    public function isQuoteEqual(Quote $quote, PaymentIntentInterface $paymentIntent): string
    {
        if ($quote->getQuoteCurrencyCode() !== $paymentIntent->getCurrencyCode()) {
            return false;
        }

        if (!$this->isAmountEqual($quote->getGrandTotal(), $paymentIntent->getGrandTotal())) {
            return false;
        }

        $quoteProducts = $this->paymentIntentsCreate->getQuoteProducts($quote);
        if (!$paymentIntent->getDetail()) return false;
        $detail = json_decode($paymentIntent->getDetail(), true);
        if (empty($detail['products'])) return false;
        return $this->getProductsForCompare($quoteProducts) === $this->getProductsForCompare($detail['products']);
    }

    protected function getProductsForCompare($products): string
    {
        $filteredData = array_map(function ($item) {
            return [
                'code' => $item['code'] ?? '',
                'sku' => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 0
            ];
        }, $products);

        usort($filteredData, function ($a, $b) {
            if ($a['code'] === $b['code']) {
                return $a['sku'] <=> $b['sku'];
            }
            return $a['code'] <=> $b['code'];
        });
        return json_encode($filteredData);
    }

    /**
     * @param string $intentId
     * @return mixed
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws JsonException
     */
    public function cancelIntent(string $intentId)
    {
        try {
            $response = $this->paymentIntentsCancel->setPaymentIntentId($intentId)->send();
        } catch (GuzzleException $exception) {
            $quote = $this->checkoutSession->getQuote();
            $this->logger->quoteError($quote, 'intents', $exception->getMessage());
            throw $exception;
        }
        return $response;
    }
}
