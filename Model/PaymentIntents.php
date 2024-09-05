<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Admin\Cards\Api\CompanyConsentsInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Logger\Logger;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Create;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Get;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\QuoteRepository;
use Exception;
use JsonException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;

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
    private OrderFactory $orderFactory;
    private OrderResourceInterface $orderResource;

    public function __construct(
        PaymentConsentsInterface $paymentConsents,
        Create                   $paymentIntentsCreate,
        Get                      $paymentIntentsGet,
        Cancel                   $paymentIntentsCancel,
        Session                  $checkoutSession,
        QuoteRepository          $quoteRepository,
        Logger                   $logger,
        UrlInterface             $urlInterface,
        PaymentIntentRepository  $paymentIntentRepository,
        OrderFactory             $orderFactory,
        OrderResourceInterface   $orderResource
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
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
    }

    /**
     * @throws AlreadyExistsException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createIntentByOrder(Order $order): array
    {
        $uid = $order->getCustomerId() ?: 0;
        if ($uid && $this->isMiniPluginExists()) {
            $uid = ObjectManager::getInstance()->get(CompanyConsentsInterface::class)->getSuperId($uid);
        }
        $airwallexCustomerId = $this->paymentConsents->getAirwallexCustomerIdInDB($uid);

        $intent = $this->paymentIntentsCreate
            ->setOrder($order, $this->urlInterface->getUrl('checkout/onepage/success'))
            ->setAirwallexCustomerId($airwallexCustomerId)
            ->send();

        $products = $this->getProducts($order);
        $shipping = $this->getShippingAddress($order);
        $billing = $this->getBillingAddress($order);

        $this->paymentIntentRepository->save(
            $order->getIncrementId(),
            $intent['id'],
            $order->getOrderCurrencyCode(),
            $order->getGrandTotal(),
            $order->getId(),
            $order->getQuoteId(),
            $order->getStore()->getId(),
            json_encode(compact('products', 'shipping', 'billing'))
        );

        return $intent;
    }

    /**
     * @throws GuzzleException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws JsonException
     */
    public function getIntentByOrder(Order $order): array
    {
        $paymentIntent = $this->paymentIntentRepository->getByOrderId($order->getId());
        if (!$paymentIntent || $this->isRequiredToGenerateIntent($order, $paymentIntent)) {
            return $this->createIntentByOrder($order);
        }
        try {
            $resp = $this->paymentIntentsGet->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            if ($e->getMessage() === AbstractClient::NOT_FOUND) {
                return $this->createIntentByOrder($order);
            }
            throw new $e;
        }
        $intentResponse = json_decode($resp, true);
        return [
            'clientSecret' => $intentResponse['client_secret'],
            'id' => $intentResponse['id'],
        ];
    }

    public function isRequiredToGenerateIntent(Order $order, PaymentIntent $paymentIntent): bool
    {
        $freshOrder = $this->orderFactory->create();
        $this->orderResource->load($freshOrder, $order->getId());
        if ($order->getStatus() !== Order::STATE_PENDING_PAYMENT) {
            return true;
        }

        if ($order->getOrderCurrencyCode() !== $paymentIntent->getCurrencyCode()) {
            return true;
        }

        if (!$this->isAmountEqual($order->getGrandTotal(), $paymentIntent->getGrandTotal())) {
            return true;
        }

        if (!$paymentIntent->getDetail()) return true;
        $detail = json_decode($paymentIntent->getDetail(), true);
        if (empty($detail['products'])) return true;

        $products = $this->paymentIntentsCreate->getProducts($order);
        return $this->getProductsForCompare($products) !== $this->getProductsForCompare($detail['products']);
    }

    public function getProductsForCompare($products): string
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
}
