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

    const CURRENCY_TO_DECIMAL = [
        "AED" => 2, "ALL" => 2, "AMD" => 2, "AOA" => 2, "ARS" => 2, "AUD" => 2, "AWG" => 2, "AZN" => 2,
        "BAM" => 2, "BBD" => 2, "BDT" => 2, "BGN" => 2, "BHD" => 3, "BMD" => 2, "BND" => 2, "BOB" => 2,
        "BRL" => 2, "BSD" => 2, "BWP" => 2, "BYN" => 2, "BZD" => 2, "CAD" => 2, "CHF" => 2, "CLP" => 0,
        "CNH" => 2, "CNY" => 2, "COP" => 2, "CRC" => 2, "CUP" => 2, "CVE" => 2, "CZK" => 2, "DJF" => 0,
        "DKK" => 2, "DOP" => 2, "DZD" => 2, "EGP" => 2, "ETB" => 2, "EUR" => 2, "FJD" => 2, "FKP" => 2,
        "GBP" => 2, "GEL" => 2, "GHS" => 2, "GIP" => 2, "GMD" => 2, "GNF" => 0, "GTQ" => 2, "GYD" => 2,
        "HKD" => 2, "HNL" => 2, "HTG" => 2, "HUF" => 2, "IDR" => 2, "ILS" => 2, "INR" => 2, "IQD" => 3,
        "ISK" => 0, "JMD" => 2, "JOD" => 3, "JPY" => 0, "KES" => 2, "KGS" => 2, "KHR" => 2, "KMF" => 0,
        "KRW" => 0, "KWD" => 3, "KYD" => 2, "KZT" => 2, "LAK" => 2, "LBP" => 2, "LKR" => 2, "LYD" => 3,
        "MAD" => 2, "MDL" => 2, "MKD" => 2, "MMK" => 2, "MNT" => 2, "MOP" => 2, "MRU" => 2, "MUR" => 2,
        "MVR" => 2, "MWK" => 2, "MXN" => 2, "MYR" => 2, "MZN" => 2, "NAD" => 2, "NGN" => 2, "NIO" => 2,
        "NOK" => 2, "NPR" => 2, "NZD" => 2, "OMR" => 3, "PAB" => 2, "PEN" => 2, "PGK" => 2, "PHP" => 2,
        "PKR" => 2, "PLN" => 2, "PYG" => 0, "QAR" => 2, "RON" => 2, "RSD" => 2, "RUB" => 2, "RWF" => 0,
        "SAR" => 2, "SBD" => 2, "SCR" => 2, "SEK" => 2, "SGD" => 2, "SHP" => 2, "SLE" => 2, "SOS" => 2,
        "SRD" => 2, "STN" => 2, "SVC" => 2, "SZL" => 2, "THB" => 2, "TND" => 3, "TOP" => 2, "TRY" => 2,
        "TTD" => 2, "TWD" => 2, "TZS" => 2, "UAH" => 2, "UGX" => 0, "USD" => 2, "UYU" => 2, "UZS" => 2,
        "VEF" => 2, "VND" => 0, "VUV" => 0, "WST" => 2, "XAF" => 0, "XCG" => 2, "XCD" => 2, "XOF" => 0,
        "XPF" => 0, "YER" => 2, "ZAR" => 2, "ZMW" => 2
    ];

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
    public function createIntent($model, string $phone, string $email, string $from, PaymentInterface $paymentMethod): array
    {
//        $uid = $order->getCustomerId() ?: 0;
//        if ($uid && $this->isMiniPluginExists()) {
//            $uid = ObjectManager::getInstance()->get(CompanyConsentsInterface::class)->getSuperId($uid);
//        }
//        $airwallexCustomerId = $this->paymentConsents->getAirwallexCustomerIdInDB($uid);
//        $create = $from === 'card_with_saved' ? $create->setAirwallexCustomerId($airwallexCustomerId) : $create->setCustomer($email, $phone);
        $isOrder = $model instanceof Order;
        $uri = 'airwallex/redirect';
        if (!$isOrder) {
            $uri .= '?quote_id=' . $model->getId();
        }
        $create = $this->paymentIntentsCreate->setIntentParams($model, $paymentMethod, $this->urlInterface->getUrl($uri));

        $intent = $create->setCustomer($email, $phone)->send();

        $products = $this->getProducts($model);
        $shipping = $this->getShippingAddress($model);
        $billing = $this->getBillingAddress($model);

        $this->paymentIntentRepository->save(
            $isOrder ? $model->getIncrementId() : $model->getReservedOrderId(),
            $intent['id'],
            $this->getCurrencyCode($model),
            $model->getGrandTotal(),
            $isOrder ? $model->getId() : 0,
            $isOrder ? $model->getQuoteId() : $model->getId(),
            $isOrder ? $model->getStore()->getId() : $model->getStoreId(),
            json_encode(compact('products', 'shipping', 'billing')),
            json_encode([$paymentMethod->getMethod()]),
        );

        return $intent;
    }



    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws JsonException
     * @throws GuzzleException
     * @throws InputException
     */
    public function getIntent($model, string $phone, string $email, string $from, PaymentInterface $paymentMethod): array
    {
        $isOrder = $model instanceof Order;
        if ($isOrder) {
            $paymentIntent = $this->paymentIntentRepository->getByOrderId($model->getId());
        } else {
            $paymentIntent = $this->paymentIntentRepository->getByQuoteId($model->getId());
        }

        $isPaymentChanged = $this->paymentIntentRepository->lastMethodCode($paymentIntent) !== $paymentMethod->getMethod();
        if (!$paymentIntent || $this->isRequiredToGenerateIntent($model, $paymentIntent) || $isPaymentChanged) {
            return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
        }

        $this->paymentIntentRepository->appendMethodCode($paymentIntent, $paymentMethod->getMethod());

        try {
            $resp = $this->paymentIntentsGet->setPaymentIntentId($paymentIntent->getIntentId())->send();
        } catch (Exception $e) {
            if ($e->getMessage() === AbstractClient::NOT_FOUND) {
                return $this->createIntent($model, $phone, $email, $from, $paymentMethod);
            }
            throw new $e;
        }
        $intentResponse = json_decode($resp, true);
        return [
            'clientSecret' => $intentResponse['client_secret'] ?? '',
            'id' => $intentResponse['id'] ?? '',
            'amount' => $intentResponse['amount'] ?? 0,
            'currency' => $intentResponse['currency'] ?? '',
        ];
    }

    public function isRequiredToGenerateIntent($model, PaymentIntent $paymentIntent): bool
    {
        if ($model instanceof Order) {
            $freshOrder = $this->orderFactory->create();
            $this->orderResource->load($freshOrder, $model->getId());
            if ($model->getStatus() !== Order::STATE_PENDING_PAYMENT) {
                return true;
            }
        }

        if ($this->getCurrencyCode($model) !== $paymentIntent->getCurrencyCode()) {
            return true;
        }

        if (!$this->isAmountEqual($model->getGrandTotal(), $paymentIntent->getGrandTotal())) {
            return true;
        }

        if (!$paymentIntent->getDetail()) return true;
        $detail = json_decode($paymentIntent->getDetail(), true);
        if (empty($detail['products'])) return true;

        $products = $this->paymentIntentsCreate->getProducts($model);
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
