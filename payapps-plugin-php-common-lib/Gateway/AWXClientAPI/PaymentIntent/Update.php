<?php
namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentIntent;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentIntent;
use Airwallex\PayappsPlugin\CommonLibrary\Util\UrlHelper;

class Update extends AbstractApi
{
    private $id;

    protected function getUri(): string
    {
        return 'pa/payment_intents/' . UrlHelper::pathSegment($this->id) . '/update';
    }

    public function setPaymentIntentId(string $paymentIntentId): Update
    {
        $this->id = $paymentIntentId;
        return $this;
    }

    public function setAdditionalInfo(array $additionalInfo): Update
    {
        return $this->setParam('additional_info', $additionalInfo);
    }

    public function setAmount(float $amount): Update
    {
        return $this->setParam('amount', $amount);
    }

    /** @param string|null $conversionQuoteId */
    public function setConversionQuoteId($conversionQuoteId = null): Update
    {
        return $this->setParam('conversion_quote_id', $conversionQuoteId);
    }

    public function setCurrency(string $currency): Update
    {
        return $this->setParam('currency', $currency);
    }

    public function setCustomer(array $customer): Update
    {
        return $this->setParam('customer', $customer);
    }

    public function setCustomerId(string $customerId): Update
    {
        return $this->setParam('customer_id', $customerId);
    }

    public function setDescriptor(string $descriptor): Update
    {
        return $this->setParam('descriptor', $descriptor);
    }

    public function setMerchantOrderId(string $merchantOrderId): Update
    {
        return $this->setParam('merchant_order_id', $merchantOrderId);
    }

    public function setMerchantWebsiteUrl(string $merchantWebsiteUrl): Update
    {
        return $this->setParam('merchant_website_url', $merchantWebsiteUrl);
    }

    public function setMetadata(array $metadata): AbstractApi
    {
        return $this->setParam('metadata', $metadata);
    }

    public function setOrder(array $order): Update
    {
        return $this->setParam('order', $order);
    }

    public function setReturnUrl(string $returnUrl): Update
    {
        return $this->setParam('return_url', $returnUrl);
    }

    protected function initializePostParams()
    {
        // Updates must leave omitted fields untouched, including metadata.
        $this->ensureRequestId();
    }

    protected function parseResponse($response): PaymentIntent
    {
        return new PaymentIntent(json_decode((string)$response->getBody(), true));
    }
}
