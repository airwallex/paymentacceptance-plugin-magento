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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\PaymentInterface;
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
     * @throws LocalizedException
     */
    public function createIntentByOrder(Order $order, string $phone, string $email, string $from, PaymentInterface $paymentMethod): array
    {
//        $uid = $order->getCustomerId() ?: 0;
//        if ($uid && $this->isMiniPluginExists()) {
//            $uid = ObjectManager::getInstance()->get(CompanyConsentsInterface::class)->getSuperId($uid);
//        }
//        $airwallexCustomerId = $this->paymentConsents->getAirwallexCustomerIdInDB($uid);
//        $create = $from === 'card_with_saved' ? $create->setAirwallexCustomerId($airwallexCustomerId) : $create->setCustomer($email, $phone);
        $create = $this->paymentIntentsCreate->setOrder($order, $paymentMethod, $this->urlInterface->getUrl('airwallex/redirect'));
        $intent = $create->setCustomer($email, $phone)->send();

        $products = $this->getProducts($order);
        $shipping = $this->getShippingAddress($order);
        $billing = $this->getBillingAddress($order);

        $currency = $this->paymentIntentsCreate->getCurrency($order, $paymentMethod);
        $isCurrencyEqual = $currency === $order->getOrderCurrencyCode();
        $this->paymentIntentRepository->save(
            $order->getIncrementId(),
            $intent['id'],
            $order->getOrderCurrencyCode(),
            $order->getGrandTotal(),
            $isCurrencyEqual ? '' : $currency,
            $isCurrencyEqual ? 0 : $this->paymentIntentsCreate->getAmount($order, $paymentMethod),
            $order->getId(),
            $order->getQuoteId(),
            $order->getStore()->getId(),
            json_encode(compact('products', 'shipping', 'billing')),
            $this->appendCodes('[]', $paymentMethod->getMethod())
        );

        return $intent;
    }

    public function appendCodes($codes, $code)
    {
        if (empty($code)) return $codes;
        $target = [];
        if (!empty($codes)) {
            $target = json_decode($codes, true);
        }
        $target[] = $code;
        return json_encode($target);
    }

    /**
     * @throws GuzzleException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws JsonException|LocalizedException
     */
    public function getIntentByOrder(Order $order, string $phone, string $email, string $from, PaymentInterface $paymentMethod): array
    {
        $paymentIntent = $this->paymentIntentRepository->getByOrderId($order->getId());
        if (!$paymentIntent || $this->isRequiredToGenerateIntent($order, $paymentIntent)) {
            return $this->createIntentByOrder($order, $phone, $email, $from, $paymentMethod);
        }
        $codes = $paymentIntent->getMethodCodes();
        $this->paymentIntentRepository->updateMethodCodes($paymentIntent, $this->appendCodes($codes, $paymentMethod->getMethod()));

        try {
            $resp = $this->paymentIntentsGet->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            if ($e->getMessage() === AbstractClient::NOT_FOUND) {
                return $this->createIntentByOrder($order, $phone, $email, $from, $paymentMethod);
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
