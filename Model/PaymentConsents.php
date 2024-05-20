<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Customer\Model\Customer;

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

    public function __construct(
        CreateCustomer $createCustomer,
        GetList $paymentConsentList,
        Disable $disablePaymentConsent,
        Retrieve $retrievePaymentConsent,
        CustomerRepositoryInterface $customerRepository,
        EavSetupFactory $eavSetupFactory,
        EncryptorInterface $encrypter
    ) {
        $this->createCustomer = $createCustomer;
        $this->paymentConsentList = $paymentConsentList;
        $this->customerRepository = $customerRepository;
        $this->disablePaymentConsent = $disablePaymentConsent;
        $this->retrievePaymentConsent = $retrievePaymentConsent;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->encrypter = $encrypter;
    }

    private function customerIdToAirwallex(int $id): string
    {
        $encrypted = $this->encrypter->encrypt((string)$id);
        return "magento-" . substr($encrypted, 0, 32) . '-' . (string)$id;
    }

    /**
     * @param int $customerId
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomer($customerId): string
    {
        $eavSetup = $this->eavSetupFactory->create([]);
        $attr = $eavSetup->getAttribute(Customer::ENTITY, self::KEY_AIRWALLEX_CUSTOMER_ID);
        if (!$attr) {
            // throw new LocalizedException(__('Airwallex Customer ID attribute not found.'));
            return '';
        }

        $idToAwx = $this->customerIdToAirwallex($customerId);

        $customer = $this->customerRepository->getById($customerId);

        $airwallexCustomerIdAttribute = $customer->getCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID);

        if ($airwallexCustomerIdAttribute && $idToAwx === $airwallexCustomerIdAttribute->getValue()) {
            return $idToAwx;
        }

        $airwallexCustomerId = $this->createCustomer->setMagentoCustomerId($idToAwx)->send();

        $this->updateCustomerId($customer, $airwallexCustomerId);

        return $airwallexCustomerId;
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
        $customer = $this->customerRepository->getById($customerId);
        $airwallexCustomerIdAttribute = $customer->getCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID);

        if (!$airwallexCustomerIdAttribute || !($airwallexCustomerId = $airwallexCustomerIdAttribute->getValue())) {
            return [];
        }

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
        $customer = $this->customerRepository->getById($customerId);
        $airwallexCustomerIdAttribute = $customer->getCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID);

        if (!$airwallexCustomerIdAttribute || !($airwallexCustomerId = $airwallexCustomerIdAttribute->getValue())) {
            throw new LocalizedException(__('Unable to verify Payment Consent ID1'));
        }

        $paymentConsent = $this->retrievePaymentConsent
            ->setPaymentConsentId($paymentConsentId)
            ->send();

        if (!$paymentConsent
            || !property_exists($paymentConsent, 'customer_id')
            || $paymentConsent->customer_id !== $airwallexCustomerId) {
            throw new LocalizedException(__('Unable to verify Payment Consent ID2'));
        }

        if ($paymentConsent->status === 'DISABLED') {
            return true;
        }

        return $this->disablePaymentConsent
            ->setPaymentConsentId($paymentConsentId)
            ->send();
    }
}
