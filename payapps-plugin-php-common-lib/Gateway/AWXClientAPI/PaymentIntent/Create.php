<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\DataSanitizationTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\AmountHelper;
use Airwallex\PayappsPlugin\CommonLibrary\Util\StringHelper;

class Create extends AbstractApi
{
    use DataSanitizationTrait;

    private $amount;

    private $currency;

    private $order;

    private $deviceData = [];

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/payment_intents/create';
    }

    /**
     * @param array $additionalInfo
     *
     * @return Create
     */
    public function setAdditionalInfo(array $additionalInfo): Create
    {
        return $this->setParam('additional_info', $additionalInfo);
    }

    /**
     * @param float $amount
     *
     * @return Create
     */
    public function setAmount(float $amount): Create
    {
        $this->amount = $amount;
        return $this->setParam('amount', $amount);
    }

    /**
     * @param string $connectedAccountId
     *
     * @return Create
     */
    public function setConnectedAccountId(string $connectedAccountId): Create
    {
        return $this->setParam('connected_account_id', $connectedAccountId);
    }

    /**
     * @param string $currency
     *
     * @return Create
     */
    public function setCurrency(string $currency): Create
    {
        $this->currency = $currency;
        return $this->setParam('currency', $currency);
    }

    /**
     * @param array $customer
     *
     * @return Create
     */
    public function setCustomer(array $customer): Create
    {
        return $this->setParam('customer', $customer);
    }

    /**
     * @param string $customerId
     *
     * @return Create
     */
    public function setCustomerId(string $customerId): Create
    {
        return $this->setParam('customer_id', $customerId);
    }

    /**
     * @param string $descriptor
     *
     * @return Create
     */
    public function setDescriptor(string $descriptor): Create
    {
        return $this->setParam('descriptor', $descriptor);
    }

    /**
     * Explicitly provided device data. Values set here take precedence over the
     * data automatically derived from the current request in initializePostParams().
     *
     * @param array $deviceData
     *
     * @return Create
     */
    public function setDeviceData(array $deviceData): Create
    {
        $this->deviceData = $deviceData;
        return $this;
    }

    /**
     * Build client device information for risk analysis, derived from the current request.
     * Caller-provided values (via setDeviceData) override the derived defaults.
     *
     * @return array
     */
    private function buildDeviceData(): array
    {
        $deviceData = [
            'browser' => [
                'java_enabled'       => false,
                'javascript_enabled' => true,
            ],
        ];

        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent !== '') {
            $deviceData['browser']['user_agent'] = $userAgent;
        }

        $acceptHeader = trim((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($acceptHeader !== '') {
            $deviceData['accept_header'] = $acceptHeader;
        }

        $ipAddress = $this->getClientIpAddress();
        if ($ipAddress !== '') {
            $deviceData['ip_address'] = $ipAddress;
        }

        $language = $this->getClientLanguage();
        if ($language !== '') {
            $deviceData['language'] = $language;
        }

        if (!empty($this->deviceData)) {
            // Merge caller-provided browser data without dropping derived keys.
            if (isset($this->deviceData['browser']) && is_array($this->deviceData['browser'])) {
                $deviceData['browser'] = array_merge($deviceData['browser'], $this->deviceData['browser']);
            }
            $deviceData = array_merge($deviceData, array_diff_key($this->deviceData, ['browser' => null]));
        }

        return $deviceData;
    }

    /**
     * Resolve the public client IP address from the current request.
     * Use REMOTE_ADDR only; host plugins can supply a resolved IP via setDeviceData().
     *
     * @return string
     */
    private function getClientIpAddress(): string
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $this->isPublicIp($remoteAddr) ? $remoteAddr : '';
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }

        // Older PHP versions miss reserved IPv6 addresses unless their spelling is canonical.
        return filter_var(
            inet_ntop($packed),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Resolve the preferred client language from the Accept-Language header.
     *
     * @return string
     */
    private function getClientLanguage(): string
    {
        $acceptLanguage = trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($acceptLanguage === '') {
            return '';
        }

        $preferredLanguage = trim(explode(',', $acceptLanguage)[0]);
        // Drop any quality value, e.g. "en-US;q=0.9".
        $preferredLanguage = trim(explode(';', $preferredLanguage)[0]);

        return $preferredLanguage;
    }

    /**
     * @param array $externalRecurringData
     *
     * @return Create
     */
    public function setExternalRecurringData(array $externalRecurringData): Create
    {
        return $this->setParam('external_recurring_data', $externalRecurringData);
    }

    /**
     * @param array $fundsSplitData
     *
     * @return Create
     */
    public function setFundsSplitData(array $fundsSplitData): Create
    {
        return $this->setParam('funds_split_data', $fundsSplitData);
    }

    /**
     * @param string $merchantOrderId
     *
     * @return Create
     */
    public function setMerchantOrderId(string $merchantOrderId): Create
    {
        return $this->setParam('merchant_order_id', $merchantOrderId);
    }

    /**
     * @param string $merchantWebsiteUrl
     *
     * @return Create
     */
    public function setMerchantWebsiteUrl(string $merchantWebsiteUrl): Create
    {
        return $this->setParam('merchant_website_url', $merchantWebsiteUrl);
    }

    /**
     * @param array $order
     *
     * @return Create
     */
    public function setOrder(array $order): Create
    {
        $this->order = $this->sanitizeOrderData($order);
        return $this;
    }

    /**
     * Sanitize order data to ensure it meets API requirements
     *
     * @param array $order
     * @return array
     */
    private function sanitizeOrderData(array $order): array
    {
        // Sanitize products
        if (isset($order['products']) && is_array($order['products'])) {
            $order['products'] = array_map(function ($product) {
                return $this->sanitizeProductData($product);
            }, $order['products']);
        }

        // Sanitize shipping
        if (isset($order['shipping']) && is_array($order['shipping'])) {
            $order['shipping'] = $this->sanitizeShippingData($order['shipping']);
        }

        return $order;
    }

    /**
     * Sanitize product data
     *
     * @param array $product
     * @return array
     */
    private function sanitizeProductData(array $product): array
    {
        $sanitized = [];

        if (isset($product['code'])) {
            $sanitized['code'] = StringHelper::sanitize($product['code'], 128);
        }

        if (isset($product['name'])) {
            $sanitized['name'] = StringHelper::sanitize($product['name'], 255);
        }

        if (isset($product['desc'])) {
            $sanitized['desc'] = StringHelper::sanitize($product['desc'], 500);
        }

        if (isset($product['sku'])) {
            $sanitized['sku'] = StringHelper::sanitize($product['sku'], 128);
        }

        if (isset($product['url'])) {
            $sanitized['url'] = StringHelper::sanitize($product['url'], 2048);
        }

        if (isset($product['unit_price'])) {
            $sanitized['unit_price'] = $product['unit_price'];
        }

        if (isset($product['quantity'])) {
            $sanitized['quantity'] = (int)$product['quantity'];
        }

        return $sanitized;
    }

    /**
     * Sanitize shipping data
     *
     * @param array $shipping
     * @return array
     */
    private function sanitizeShippingData(array $shipping): array
    {
        $sanitized = [];

        if (isset($shipping['first_name'])) {
            $sanitized['first_name'] = StringHelper::sanitize($shipping['first_name'], 128);
        }

        if (isset($shipping['last_name'])) {
            $sanitized['last_name'] = StringHelper::sanitize($shipping['last_name'], 128);
        }

        if (isset($shipping['phone_number'])) {
            $sanitized['phone_number'] = StringHelper::sanitize($shipping['phone_number'], 50, false);
        }

        if (isset($shipping['shipping_method'])) {
            $sanitized['shipping_method'] = StringHelper::sanitize($shipping['shipping_method'], 128);
        }

        if (isset($shipping['fee_amount'])) {
            $sanitized['fee_amount'] = $shipping['fee_amount'];
        }

        if (isset($shipping['address']) && is_array($shipping['address'])) {
            $sanitized['address'] = $this->sanitizeAddressData($shipping['address']);
        }

        return $sanitized;
    }

    /**
     * @param array $paymentMethod
     *
     * @return Create
     */
    public function setPaymentMethod(array $paymentMethod): Create
    {
        return $this->setParam('payment_method', $paymentMethod);
    }

    /**
     * @param array $paymentMethodOptions
     *
     * @return Create
     */
    public function setPaymentMethodOptions(array $paymentMethodOptions): Create
    {
        return $this->setParam('payment_method_options', $paymentMethodOptions);
    }

    /**
     * @param string $returnUrl
     *
     * @return Create
     */
    public function setReturnUrl(string $returnUrl): Create
    {
        return $this->setParam('return_url', $returnUrl);
    }

    /**
     * @param array $riskControlOptions
     *
     * @return Create
     */
    public function setRiskControlOptions(array $riskControlOptions): Create
    {
        return $this->setParam('risk_control_options', $riskControlOptions);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function initializePostParams()
    {
        // Format amount according to currency decimal places
        if ($this->amount && $this->currency) {
            $amount = AmountHelper::formatAmount($this->amount, (string)$this->currency);
            $this->setParam('amount', $amount);
        }

        if ($this->amount && $this->currency && $this->order) {
            $orderItemTotal = 0;

            if (!empty($this->order['products'])) {
                foreach ($this->order['products'] as $product) {
                    $unitPrice = $product['unit_price'] ?? 0;
                    $quantity = $product['quantity'] ?? 1;
                    $orderItemTotal += $unitPrice * $quantity;
                }
            }

            if (isset($this->order['shipping']) && isset($this->order['shipping']['fee_amount'])) {
                $orderItemTotal += $this->order['shipping']['fee_amount'] ?? 0;
            }

            // Amounts have at most four decimal places; ignore floating-point residue.
            $shortfall = round($amount - $orderItemTotal, 4);
            if ($shortfall > 0) {
                $diff = AmountHelper::formatAmount($shortfall, $this->currency);
                if ($diff < $shortfall) {
                    $decimalPlaces = PaymentIntent::CURRENCY_TO_DECIMAL[strtoupper($this->currency)] ?? 2;
                    $diff = AmountHelper::formatAmount($diff + pow(10, -$decimalPlaces), $this->currency);
                }
                $this->order['products'][] = [
                    'name'       => 'Other Fees',
                    'desc'       => '',
                    'quantity'   => 1,
                    'sku'        => '',
                    'unit_price' => $diff,
                ];
            }

            $this->setParam('order', $this->order);
        }

        $this->setParam('device_data', $this->buildDeviceData());

        parent::initializePostParams();
    }

    /**
     * @param $response
     *
     * @return PaymentIntent
     */
    protected function parseResponse($response): PaymentIntent
    {
        return new PaymentIntent(json_decode((string)$response->getBody(), true));
    }
}