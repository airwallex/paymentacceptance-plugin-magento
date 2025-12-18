<?php

namespace Airwallex\Payments\Model;

use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentConsent as StructPaymentConsent;
use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\CommonLibraryInit;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer\Create as CreateCustomer;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentConsent\All as AllPaymentConsents;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent\Retrieve as RetrievePaymentConsent;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Eav\Setup\EavSetupFactory;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer\GenerateClientSecret as RetrieveCustomerClientSecret;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\CustomerClientSecret as StructCustomerClientSecret;
use Airwallex\Payments\Api\Data\ClientSecretResponseInterfaceFactory;
use Airwallex\Payments\Api\Data\ClientSecretResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer\Retrieve as RetrieveCustomer;
use Magento\Framework\Api\FilterBuilder;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Indexer\IndexerRegistry;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\PaymentConsent\Disable as DisablePaymentConsent;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Customer as StructCustomer;
use Airwallex\Payments\Api\Data\SavedPaymentResponseInterfaceFactory;

class PaymentConsents implements PaymentConsentsInterface
{
    use HelperTrait;
    public const STATUS_VERIFIED = 'VERIFIED';

    public const KEY_AIRWALLEX_CUSTOMER_ID = 'airwallex_customer_id';

    private CreateCustomer $createCustomer;
    private AllPaymentConsents $paymentConsentList;
    private CustomerRepositoryInterface $customerRepository;
    private DisablePaymentConsent $disablePaymentConsent;
    private RetrievePaymentConsent $retrievePaymentConsent;
    private EavSetupFactory $eavSetupFactory;
    private EncryptorInterface $encryptor;
    private PaymentTokenRepositoryInterface $tokenRepository;
    private PaymentTokenFactoryInterface $tokenFactory;
    private StoreManagerInterface $storeManager;
    private PaymentTokenManagement $tokenManagement;
    private RetrieveCustomerClientSecret $retrieveCustomerClientSecret;
    private ClientSecretResponseInterfaceFactory $clientSecretResponseFactory;
    private RetrieveCustomer $retrieveCustomer;
    protected FilterBuilder $filterBuilder;
    protected SearchCriteriaBuilder $searchCriteriaBuilder;
    protected CustomerFactory $customerFactory;
    protected CustomerResource $customerResource;
    protected IndexerRegistry $indexerRegistry;
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    private SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory;

    public function __construct(
        CreateCustomer                       $createCustomer,
        AllPaymentConsents                   $paymentConsentList,
        DisablePaymentConsent                $disablePaymentConsent,
        RetrievePaymentConsent               $retrievePaymentConsent,
        CustomerRepositoryInterface          $customerRepository,
        EavSetupFactory                      $eavSetupFactory,
        EncryptorInterface                   $encryptor,
        PaymentTokenRepositoryInterface      $tokenRepository,
        PaymentTokenManagement               $tokenManagement,
        PaymentTokenFactoryInterface         $tokenFactory,
        StoreManagerInterface                $storeManager,
        RetrieveCustomerClientSecret         $retrieveCustomerClientSecret,
        ClientSecretResponseInterfaceFactory $clientSecretResponseFactory,
        RetrieveCustomer                     $retrieveCustomer,
        FilterBuilder                        $filterBuilder,
        SearchCriteriaBuilder                $searchCriteriaBuilder,
        CustomerFactory                      $customerFactory,
        CustomerResource                     $customerResource,
        IndexerRegistry                      $indexerRegistry,
        AvailablePaymentMethodsHelper        $availablePaymentMethodsHelper,
        SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory,
        CommonLibraryInit                    $commonLibraryInit
    )
    {
        $this->createCustomer = $createCustomer;
        $this->paymentConsentList = $paymentConsentList;
        $this->customerRepository = $customerRepository;
        $this->disablePaymentConsent = $disablePaymentConsent;
        $this->retrievePaymentConsent = $retrievePaymentConsent;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->encryptor = $encryptor;
        $this->tokenRepository = $tokenRepository;
        $this->tokenManagement = $tokenManagement;
        $this->tokenFactory = $tokenFactory;
        $this->storeManager = $storeManager;
        $this->retrieveCustomerClientSecret = $retrieveCustomerClientSecret;
        $this->clientSecretResponseFactory = $clientSecretResponseFactory;
        $this->retrieveCustomer = $retrieveCustomer;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->indexerRegistry = $indexerRegistry;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->savedPaymentResponseFactory = $savedPaymentResponseFactory;
        $commonLibraryInit->exec();
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function generateAirwallexCustomerId(CustomerInterface $customer): string
    {
        $timestamp = time();
        $id = $customer->getId();
        $rand = rand(100000, 999999);
        $str = $timestamp . '-' . $id . '-' . $rand;
        return substr($str, 0, 64);
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomer(CustomerInterface $customer): string
    {
        try {
            $eavSetup = $this->eavSetupFactory->create();
            $attr = $eavSetup->getAttribute(Customer::ENTITY, self::KEY_AIRWALLEX_CUSTOMER_ID);
            if (!$attr) {
                return '';
            }

            $lines = ObjectManager::getInstance()->get(ScopeConfigInterface::class)->getValue('customer/address/street_lines');
            foreach ($customer->getAddresses() as $address) {
                if (count($address->getStreet()) > $lines) {
                    $address->setStreet([implode(', ', $address->getStreet())]);
                }
            }

            $airwallexCustomerId = $this->tryFindCustomerInVault($customer);
            if ($airwallexCustomerId) {
                $this->updateCustomerId($customer, $airwallexCustomerId);
                return $airwallexCustomerId;
            }

            /** @var StructCustomer $airwallexCustomer */
            $airwallexCustomer = $this->createCustomer->setCustomerId($customer->getId())->send();
            $airwallexCustomerId = $airwallexCustomer->getId();

            $this->updateCustomerId($customer, $airwallexCustomerId);
            return $airwallexCustomerId;
        } catch (Exception $e) {
            RemoteLog::error("Create Airwallex Customer failed {$customer->getId()}: " . $e->getMessage(), 'onCreateAirwallexCustomerError');
            return '';
        }
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    private function tryFindCustomerInVault(CustomerInterface $customer): string
    {
        $customerId = $customer->getId();
        $tokens = $this->getTokens($customerId) ?: array();
        $ids = array();
        foreach ($tokens as $token) {
            $detail = $token->getTokenDetails();
            $arr = json_decode($detail, true);
            if (!empty($arr['customer_id'])) {
                $ids[$arr['customer_id']] = true;
            }
        }

        $ids = array_keys($ids);
        $oldAirwallexCustomerId = '';
        foreach ($ids as $id) {
            try {
                $this->retrieveCustomer->setCustomerId($id)->send();
                $oldAirwallexCustomerId = $id;
                break;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage(), true);
                if (is_array($error) && isset($error['code']) && $error['code'] === AbstractApi::ERROR_RESOURCE_NOT_FOUND) {
                    continue;
                }
                return '';
            }
        }
        return $oldAirwallexCustomerId;
    }

    /**
     * @param int $customerId
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomerById(int $customerId): string
    {
        if ($target = $this->getAirwallexCustomerIdInDB($customerId)) {
            return $target;
        }
        return $this->createAirwallexCustomer($this->customerRepository->getById($customerId));
    }

    /**
     * @param CustomerInterface $customerData
     * @param string $airwallexCustomerId
     * @throws Exception
     */
    protected function updateCustomerId(CustomerInterface $customerData, string $airwallexCustomerId)
    {
        $customer = $this->customerFactory->create();
        $this->customerResource->load($customer, $customerData->getId());
        $customer->setData(self::KEY_AIRWALLEX_CUSTOMER_ID, $airwallexCustomerId);
        $this->customerResource->saveAttribute($customer, self::KEY_AIRWALLEX_CUSTOMER_ID);
        $indexer = $this->indexerRegistry->get('customer_grid');
        if (!$indexer->isInvalid()) {
            $indexer->reindexRow($customer->getId());
        }
    }

    /**
     * @param int $customerId
     * @return SavedPaymentResponseInterface[]|array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function getSavedCards(int $customerId): array
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) return [];

        $paymentConsents = $this->paymentConsentList
            ->setCustomerId($airwallexCustomerId)
            ->setNextTriggeredBy(StructPaymentConsent::TRIGGERED_BY_CUSTOMER)
            ->get();

        $result = [];
        /** @var StructPaymentConsent $paymentConsent */
        foreach ($paymentConsents as $paymentConsent) {
            if (empty($paymentConsent->getPaymentMethod()) || empty($paymentConsent->getPaymentMethod()['card'])) {
                continue;
            }

            $cardBrand = strtolower($paymentConsent->getCardBrand());
            if ($cardBrand === 'american express') {
                $cardBrand = 'amex';
            }

            $cardLogos = $this->availablePaymentMethodsHelper->getCardLogos();
            /** @var SavedPaymentResponse $result */
            $savedPayment = $this->savedPaymentResponseFactory->create();
            $savedPayment->setData([
                SavedPaymentResponseInterface::DATA_KEY_ID => $paymentConsent->getId(),
                SavedPaymentResponseInterface::DATA_KEY_CARD_BRAND => $cardBrand,
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_MONTH => $paymentConsent->getCardExpiryMonth(),
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_YEAR => $paymentConsent->getCardExpiryYear(),
                SavedPaymentResponseInterface::DATA_KEY_CARD_LAST_FOUR => $paymentConsent->getCardLast4(),
                SavedPaymentResponseInterface::DATA_KEY_NEXT_TRIGGERED_BY => $paymentConsent->getNextTriggeredBy(),
                SavedPaymentResponseInterface::DATA_KEY_NUMBER_TYPE => $paymentConsent->getCardNumberType(),
                SavedPaymentResponseInterface::DATA_KEY_CARD_ICON => $cardLogos[$cardBrand] ?? '',
                SavedPaymentResponseInterface::DATA_KEY_PAYMENT_METHOD_ID => $paymentConsent->getPaymentMethod()['id'] ?? '',
                SavedPaymentResponseInterface::DATA_STATUS => $paymentConsent->getStatus(),
                SavedPaymentResponseInterface::DATA_BILLING => json_encode($paymentConsent->getCardBilling()),
            ]);

            $result[] = $savedPayment;
        }
        return $result;
    }

    /**
     * @param int $customerId
     * @param string $paymentConsentId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws RequestException
     */
    public function disablePaymentConsent(int $customerId, string $paymentConsentId): bool
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return false;
        }

        /** @var StructPaymentConsent $paymentConsent */
        $paymentConsent = $this->retrievePaymentConsent
            ->setPaymentConsentId($paymentConsentId)
            ->send();

        if (
            !$paymentConsent || $paymentConsent->getCustomerId() !== $airwallexCustomerId
        ) {
            throw new LocalizedException(__('Unable to verify Payment Consent ID'));
        }

        if ($paymentConsent->getStatus() === 'DISABLED') {
            return true;
        }

        if ($token = $this->tokenManagement->getByGatewayToken($paymentConsentId, Vault::CODE, $customerId)) {
            $this->tokenRepository->delete($token);
        }

        try {
            /** @var StructPaymentConsent $deletedPaymentConsent */
            $deletedPaymentConsent = $this->disablePaymentConsent
                ->setPaymentConsentId($paymentConsentId)
                ->send();
            return !!$deletedPaymentConsent->getId();
        } catch (Exception $e) {
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param int $customerId
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function cloudCards(int $customerId): array
    {
        $cards = $this->getSavedCards($customerId);
        if (!$cards) {
            return [];
        }
        $cloudCards = [];
        foreach ($cards as $card) {
            $brand = $card->getCardBrand();
            if (strtolower($brand) === 'american express') $brand = "amex";
            $cloudCards[$card->getId()] = [
                'card_brand' => $brand,
                'card_expiry_month' => $card->getCardExpiryMonth(),
                'card_expiry_year' => $card->getCardExpiryYear(),
                'card_last_four' => $card->getCardLastFour(),
                'card_icon' => $this->availablePaymentMethodsHelper->getCardLogos()[$brand] ?? '',
            ];
        }
        return $cloudCards;
    }

    /**
     * @param ?int $customerId
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAirwallexCustomerIdInDB(?int $customerId): string
    {
        if (!$customerId) return "";
        $customer = $this->customerRepository->getById($customerId);
        $airwallexCustomerIdAttribute = $customer->getCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID);

        $airwallexCustomerId = '';
        if ($airwallexCustomerIdAttribute && $airwallexCustomerIdAttribute->getValue()) {
            $airwallexCustomerId = $airwallexCustomerIdAttribute->getValue();
        }
        return $airwallexCustomerId;
    }

    /**
     * @param int $customerId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function syncVault(int $customerId): bool
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return false;
        }

        $cloudCards = $this->cloudCards($customerId);

        $tokens = $this->tokenManagement->getVisibleAvailableTokens($customerId);
        $dbCards = [];
        foreach ($tokens as $token) {
            $detail = $token->getTokenDetails();
            $arr = json_decode($detail, true);
            $tokenAirwallexCustomerId = $arr['customer_id'] ?? '';
            if (empty($cloudCards[$token->getGatewayToken()]) && $tokenAirwallexCustomerId === $airwallexCustomerId) {
                $this->tokenRepository->delete($token);
                continue;
            }

            $dbCards[$token->getGatewayToken()] = true;
        }


        $code = Vault::CODE;
        $type = 'card';

        $cloudCardsTokens = [];
        foreach ($cloudCards as $index => $cloudCard) {
            $cloudCardsTokens[$index] = true;
            if (empty($dbCards[$index])) {
                $token = $this->tokenFactory->create();
                $token->setCustomerId($customerId);
                $token->setWebsiteId($this->storeManager->getStore()->getWebsiteId());
                $token->setPaymentMethodCode($code);
                $token->setType($type);
                $token->setGatewayToken($index);
                $details = json_encode([
                    'type' => $cloudCard['card_brand'],
                    'icon' => $cloudCard['card_icon'],
                    'status' => PaymentConsents::STATUS_VERIFIED,
                    'id' => $index,
                    'customer_id' => $airwallexCustomerId,
                    'maskedCC' => $cloudCard['card_last_four'],
                    'expirationDate' => $cloudCard['card_expiry_month'] . '/' . $cloudCard['card_expiry_year'],
                ]);
                $token->setTokenDetails($details);
                $hash = $this->encryptor->getHash($customerId . $code . $type . $details);
                $token->setPublicHash($hash);
                $token->setExpiresAt(
                    sprintf(
                        '%s-%s-01 00:00:00',
                        $cloudCard['card_expiry_year'],
                        $cloudCard['card_expiry_month']
                    )
                );
                try {
                    $this->tokenRepository->save($token);
                } catch (Exception $e) {
                }
            }
        }

        $allTokens = $this->tokenManagement->getListByCustomerId($customerId);
        /** @var PaymentTokenInterface $token */
        foreach ($allTokens as $token) {
            $detail = $token->getTokenDetails();
            if (!$detail) continue;
            $arr = json_decode($detail, true);
            if (!empty($cloudCardsTokens[$token->getGatewayToken()])) {
                if (!empty($arr['status']) && $arr['status'] === PaymentConsents::STATUS_VERIFIED) {
                    continue;
                }
                $arr['status'] = PaymentConsents::STATUS_VERIFIED;
            } else {
                if (!empty($arr['status']) && $arr['status'] === 'NOT-' . PaymentConsents::STATUS_VERIFIED) {
                    continue;
                }
                $arr['status'] = 'NOT-' . PaymentConsents::STATUS_VERIFIED;
            }
            $details = json_encode($arr);
            $token->setTokenDetails($details);
            $this->tokenRepository->save($token);
        }

        return true;
    }

    /**
     * @param int $customerId
     * @return ClientSecretResponseInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws RequestException
     */
    public function generateClientSecret(int $customerId): ClientSecretResponseInterface
    {
        $response = $this->clientSecretResponseFactory->create();

        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return $response;
        }

        /** @var StructCustomerClientSecret $customerClientSecret */
        $customerClientSecret = $this->retrieveCustomerClientSecret
            ->setCustomerId($airwallexCustomerId)
            ->send();

        $response->setData([
            ClientSecretResponseInterface::DATA_KEY_CLIENT_SECRET => $customerClientSecret->getClientSecret(),
            ClientSecretResponseInterface::DATA_KEY_EXPIRED_TIME => $customerClientSecret->getExpiredTime(),
        ]);
        return $response;
    }

    /**
     * @return ClientSecretResponseInterface
     * @throws RequestException
     * @throws Exception
     */
    public function guestGenerateClientSecret(): ClientSecretResponseInterface
    {
        $response = $this->clientSecretResponseFactory->create();

        /** @var StructCustomer $airwallexCustomer */
        $airwallexCustomer = $this->createCustomer->setCustomerId(0)->send();

        $response->setData([
            ClientSecretResponseInterface::DATA_KEY_CLIENT_SECRET => $airwallexCustomer->getClientSecret(),
            ClientSecretResponseInterface::DATA_KEY_CUSTOMER_ID => $airwallexCustomer->getId(),
        ]);
        return $response;
    }

    /**
     * getTokens
     *
     * @param int $customerId
     * @return PaymentTokenInterface[]
     */
    public function getTokens(int $customerId): array
    {
        $customerFilter = [
            $this->filterBuilder->setField(PaymentTokenInterface::CUSTOMER_ID)
                ->setValue($customerId)
                ->create()
        ];
        $searchCriteria = $this->searchCriteriaBuilder->addFilters($customerFilter)->create();

        return $this->tokenRepository->getList($searchCriteria)->getItems();
    }
}
