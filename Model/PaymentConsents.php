<?php

namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Model\Client\Request\CreateCustomer;
use Airwallex\Payments\Model\Client\Request\PaymentConsent\Disable;
use Airwallex\Payments\Model\Client\Request\PaymentConsent\GetList;
use Airwallex\Payments\Model\Client\Request\PaymentConsent\Retrieve;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Eav\Setup\EavSetupFactory;
use Exception;
use Magento\Customer\Model\Customer;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\Client\Request\RetrieveCustomerClientSecret;
use Airwallex\Payments\Api\Data\ClientSecretResponseInterfaceFactory;
use Airwallex\Payments\Api\Data\ClientSecretResponseInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Airwallex\Payments\Model\Client\Request\RetrieveCustomer;
use Magento\Framework\Api\FilterBuilder;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PaymentConsents implements PaymentConsentsInterface
{
    public const CUSTOMER_ID_PREFIX = 'magento_';

    public const KEY_AIRWALLEX_CUSTOMER_ID = 'airwallex_customer_id';

    private CreateCustomer $createCustomer;
    private GetList $paymentConsentList;
    private CustomerRepositoryInterface $customerRepository;
    private Disable $disablePaymentConsent;
    private Retrieve $retrievePaymentConsent;
    private EavSetupFactory $eavSetupFactory;
    private EncryptorInterface $encrypter;
    private PaymentTokenRepositoryInterface $tokenRepository;
    private PaymentTokenFactoryInterface $tokenFactory;
    private StoreManagerInterface $storeManager;
    private PaymentTokenManagement $tokenManagement;
    private RetrieveCustomerClientSecret $retrieveCustomerClientSecret;
    private ClientSecretResponseInterfaceFactory $clientSecretResponseFactory;
    private RetrieveCustomer $retrieveCustomer;
    protected FilterBuilder $filterBuilder;
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        CreateCustomer $createCustomer,
        GetList $paymentConsentList,
        Disable $disablePaymentConsent,
        Retrieve $retrievePaymentConsent,
        CustomerRepositoryInterface $customerRepository,
        EavSetupFactory $eavSetupFactory,
        EncryptorInterface $encrypter,
        PaymentTokenRepositoryInterface $tokenRepository,
        PaymentTokenManagement $tokenManagement,
        PaymentTokenFactoryInterface $tokenFactory,
        StoreManagerInterface $storeManager,
        RetrieveCustomerClientSecret $retrieveCustomerClientSecret,
        ClientSecretResponseInterfaceFactory $clientSecretResponseFactory,
        RetrieveCustomer $retrieveCustomer,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->createCustomer = $createCustomer;
        $this->paymentConsentList = $paymentConsentList;
        $this->customerRepository = $customerRepository;
        $this->disablePaymentConsent = $disablePaymentConsent;
        $this->retrievePaymentConsent = $retrievePaymentConsent;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->encrypter = $encrypter;
        $this->tokenRepository = $tokenRepository;
        $this->tokenManagement = $tokenManagement;
        $this->tokenFactory = $tokenFactory;
        $this->storeManager = $storeManager;
        $this->retrieveCustomerClientSecret = $retrieveCustomerClientSecret;
        $this->clientSecretResponseFactory = $clientSecretResponseFactory;
        $this->retrieveCustomer = $retrieveCustomer;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     */
    public function generateAirwallexCustomerId($customer)
    {
        $timestamp = time();
        $id = $customer->getId();
        $rand = rand(100000, 999999);
        $str = (string)$timestamp . '-' . (string)$id . '-' . (string)$rand;
        return substr($str, 0, 64);
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomer($customer): string
    {
        $eavSetup = $this->eavSetupFactory->create([]);
        $attr = $eavSetup->getAttribute(Customer::ENTITY, self::KEY_AIRWALLEX_CUSTOMER_ID);
        if (!$attr) {
            // throw new LocalizedException(__('Airwallex Customer ID attribute not found.'));
            return '';
        }

        $airwallexCustomerId = $this->tryFindCustomerInVault($customer);
        if ($airwallexCustomerId) {
            $this->updateCustomerId($customer, $airwallexCustomerId);
            // $this->syncVault($customer->getId());
            return $airwallexCustomerId;
        }

        try {
            $airwallexCustomerId = $this->createCustomer->setMagentoCustomerId($this->generateAirwallexCustomerId($customer))->send();
        } catch (Exception $e) {
            return '';
        }
        $this->updateCustomerId($customer, $airwallexCustomerId);
        return $airwallexCustomerId;
    }

    /**
     * @param CustomerInterface $customer
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function tryFindCustomerInVault($customer)
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
                $this->retrieveCustomer->setAirwallexCustomerId($id)->send();
                $oldAirwallexCustomerId = $id;
                break;
            } catch (Exception $e) {
                if ($this->retrieveCustomer::NOT_FOUND === $e->getMessage()) {
                    continue;
                }
                return '';
                // throw $e;
            }
        }
        return $oldAirwallexCustomerId;
    }

    /**
     * @param int $customerId
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomerById($customerId): string
    {
        if ($target = $this->getAirwallexCustomerIdInDB($customerId)) {
            return $target;
        }
        return $this->createAirwallexCustomer($this->customerRepository->getById($customerId));
    }

    /**
     * @param CustomerInterface $customer
     * @param string $airwallexCustomerId
     * @throws InputMismatchException
     * @throws InputException
     * @throws LocalizedException
     */
    protected function updateCustomerId(CustomerInterface $customer, string $airwallexCustomerId)
    {
        $customer->setCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID, $airwallexCustomerId);

        $this->customerRepository->save($customer);
    }

    /**
     * @param int $customerId
     * @return SavedPaymentResponseInterface[]|array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSavedCards($customerId)
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) return [];

        return $this->paymentConsentList
            ->setCustomerId($airwallexCustomerId)
            ->setPage(0, 200)
            //            ->setNextTriggeredBy(GetList::TRIGGERED_BY_CUSTOMER)
            //            ->setTriggerReason(GetList::TRIGGER_REASON_UNSCHEDULED)
            ->send();
    }

    /**
     * @param int $customerId
     * @param string $paymentConsentId
     * @return bool
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function disablePaymentConsent($customerId, $paymentConsentId): bool
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return false;
        }

        $paymentConsent = $this->retrievePaymentConsent
            ->setPaymentConsentId($paymentConsentId)
            ->send();

        if (
            !$paymentConsent
            || !property_exists($paymentConsent, 'customer_id')
            || $paymentConsent->customer_id !== $airwallexCustomerId
        ) {
            throw new LocalizedException(__('Unable to verify Payment Consent ID2'));
        }

        if ($paymentConsent->status === 'DISABLED') {
            return true;
        }

        if ($token = $this->tokenManagement->getByGatewayToken($paymentConsentId, Vault::CODE, $customerId)) {
            $this->tokenRepository->delete($token);
        }

        return $this->disablePaymentConsent
            ->setPaymentConsentId($paymentConsentId)
            ->send();
    }

    /**
     * @param int $customerId
     * @return array
     * @throws \Exception
     */
    private function cloudCards($customerId)
    {
        $cards = $this->getSavedCards($customerId);
        if (!$cards) {
            return [];
        }
        $cloudCards = [];
        foreach ($cards as $card) {
            $cloudCards[$card->getId()] = [
                'card_brand' => $card->getCardBrand(),
                'card_expiry_month' => $card->getCardExpiryMonth(),
                'card_expiry_year' => $card->getCardExpiryYear(),
                'card_last_four' => $card->getCardLastFour(),
                'card_icon' => $card->getCardIcon(),
            ];
        }
        return $cloudCards;
    }

    /**
     * @param int $customerId
     * @return string
     */
    public function getAirwallexCustomerIdInDB($customerId)
    {
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
     */
    public function syncVault($customerId)
    {
        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return false;
        }

        try {
            $cloudCards = $this->cloudCards($customerId);
        } catch (\Exception $e) {
            return false;
        }

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


        foreach ($cloudCards as $index => $cloudCard) {
            if (empty($dbCards[$index])) {
                // if ($token = $this->tokenManagement->getByGatewayToken($index, Vault::CODE, $customerId)) {
                //     try {
                //         $this->disablePaymentConsent($customerId, $index);
                //     } catch (\Exception $e) {};
                //     continue;
                // }
                $token = $this->tokenFactory->create();
                $token->setCustomerId($customerId);
                $token->setWebsiteId($this->storeManager->getStore()->getWebsiteId());
                $token->setPaymentMethodCode($code);
                $token->setType($type);
                $token->setGatewayToken($index);
                $details = json_encode([
                    'type' => $cloudCard['card_brand'],
                    'icon' => $cloudCard['card_icon'],
                    'id' => $index,
                    'customer_id' => $airwallexCustomerId,
                    'maskedCC' => $cloudCard['card_last_four'],
                    'expirationDate' => $cloudCard['card_expiry_month'] . '/' . $cloudCard['card_expiry_year'],
                ]);
                $token->setTokenDetails($details);
                $hash = $this->encrypter->getHash($customerId . $code . $type . $details);
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
                } catch (\Exception $e) {
                }
            }
        }

        return true;
    }
    /**
     * @param int $customerId
     * @return ClientSecretResponseInterface
     */
    public function generateClientSecret($customerId)
    {
        $response = $this->clientSecretResponseFactory->create();

        $airwallexCustomerId = $this->getAirwallexCustomerIdInDB($customerId);
        if (!$airwallexCustomerId) {
            return $response;
        }

        $data = $this->retrieveCustomerClientSecret
            ->setCustomerId($airwallexCustomerId)
            ->send();

        $response->setData([
            ClientSecretResponseInterface::DATA_KEY_CLIENT_SECRET => $data->client_secret,
            ClientSecretResponseInterface::DATA_KEY_EXPIRED_TIME => $data->expired_time,
        ]);
        return $response;
    }



    /**
     * getTokens
     *
     * @param int $customerId
     * @return Data\PaymentTokenInterface[]
     */
    public function getTokens($customerId)
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
