<?php

namespace Airwallex\Payments\Model\Client\Request\PaymentConsent;

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Airwallex\Payments\Api\Data\SavedPaymentResponseInterfaceFactory;
use Airwallex\Payments\Helper\AuthenticationHelper;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Guzzle\RequestLogger;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\SavedPaymentResponse;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Http\Message\ResponseInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Checkout\Helper\Data as CheckoutData;

class GetList extends AbstractClient implements BearerAuthenticationInterface
{
    public const TRIGGER_REASON_SCHEDULED = 'scheduled';
    public const TRIGGER_REASON_UNSCHEDULED = 'unscheduled';
    public const TRIGGERED_BY_CUSTOMER = 'customer';
    public const TRIGGERED_BY_MERCHANT = 'merchant';

    private SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory;
    private AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    protected CheckoutData $checkoutData;

    public function __construct(
        AuthenticationHelper                 $authenticationHelper,
        IdentityService                      $identityService,
        RequestLogger                        $requestLogger,
        Configuration                        $configuration,
        ModuleListInterface                  $moduleList,
        ProductMetadataInterface             $productMetadata,
        SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory,
        CheckoutData                         $checkoutData,
        CacheInterface                       $cache,
        AvailablePaymentMethodsHelper        $availablePaymentMethodsHelper
    )
    {
        parent::__construct($authenticationHelper, $identityService, $requestLogger, $configuration, $productMetadata, $moduleList, $checkoutData, $cache);
        $this->savedPaymentResponseFactory = $savedPaymentResponseFactory;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents';
    }

    /**
     * @param string $airwallexCustomerId
     * @return AbstractClient|GetList
     */
    public function setCustomerId(string $airwallexCustomerId)
    {
        return $this->setParam('customer_id', $airwallexCustomerId);
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     * @return AbstractClient|GetList
     */
    public function setPage(int $pageNumber, int $pageSize = 20)
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param string $status
     * @return AbstractClient|GetList
     */
    public function setStatus(string $status)
    {
        return $this->setParam('status', $status);
    }

    /**
     * @param string $triggerReason
     * @return AbstractClient|GetList
     */
    public function setTriggerReason(string $triggerReason)
    {
        return $this->setParam('merchant_trigger_reason', $triggerReason);
    }

    /**
     * @param string $triggeredBy
     * @return AbstractClient|GetList
     */
    public function setNextTriggeredBy(string $triggeredBy)
    {
        return $this->setParam('next_triggered_by', $triggeredBy);
    }

    /**
     * @return array|mixed
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getCardSchemes()
    {
        $methods = $this->availablePaymentMethodsHelper->getAllPaymentMethodTypes();
        if (!$methods) {
            return [];
        }
        foreach ($methods as $method) {
            if ($method['name'] === 'card') {
                return $method['card_schemes'];
            }
        }
        return [];
    }

    /**
     * @param ResponseInterface $response
     *
     * @return SavedPaymentResponseInterface[]
     * @throws JsonException|GuzzleException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $response = $this->parseJson($response);

        $result = [];
        foreach ($response->items as $item) {
            if (!property_exists($item, 'payment_method')
                || !property_exists($item->payment_method, 'card')) {
                continue;
            }

            $cards = [];
            foreach ($this->getCardSchemes() as $scheme) {
                $cards[$scheme['name']] = $scheme;
            }
            $iconIndex = strtolower($item->payment_method->card->brand);
            if ($iconIndex === 'american express') {
                $iconIndex = 'amex';
            }

            /** @var SavedPaymentResponse $result */
            $savedPayment = $this->savedPaymentResponseFactory->create();
            $billing = '';
            if (!empty($item->payment_method->card->billing)) {
                $billing = json_encode((array)$item->payment_method->card->billing);
            }
            $savedPayment->setData([
                SavedPaymentResponseInterface::DATA_KEY_ID => $item->id,
                SavedPaymentResponseInterface::DATA_KEY_CARD_BRAND => $iconIndex,
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_MONTH => $item->payment_method->card->expiry_month,
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_YEAR => $item->payment_method->card->expiry_year,
                SavedPaymentResponseInterface::DATA_KEY_CARD_LAST_FOUR => $item->payment_method->card->last4,
                SavedPaymentResponseInterface::DATA_KEY_CARD_HOLDER_NAME => $item->payment_method->card->name,
                SavedPaymentResponseInterface::DATA_KEY_NEXT_TRIGGERED_BY => $item->next_triggered_by ?? '',
                SavedPaymentResponseInterface::DATA_KEY_NUMBER_TYPE => $item->payment_method->card->number_type ?? '',
                SavedPaymentResponseInterface::DATA_KEY_CARD_ICON => $cards[$iconIndex]['resources']['logos']['png'] ?? '',
                SavedPaymentResponseInterface::DATA_KEY_PAYMENT_METHOD_ID => $item->payment_method->id ?? '',
                SavedPaymentResponseInterface::DATA_STATUS => $item->status,
                SavedPaymentResponseInterface::DATA_BILLING => $billing
            ]);

            $result[] = $savedPayment;
        }

        return [
            'has_more' => $response->has_more,
            'items' => $result,
        ];
    }
}
