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

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Model\Client\Request\CreateCustomer;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;

class PaymentConsents implements PaymentConsentsInterface
{
    const CUSTOMER_ID_PREFIX = 'magento_';

    const KEY_AIRWALLEX_CUSTOMER_ID = 'airwallex_customer_id';

    private CreateCustomer $createCustomer;
    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        CreateCustomer $createCustomer,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->createCustomer = $createCustomer;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param int $customerId
     * @return string
     * @throws GuzzleException
     * @throws \JsonException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createAirwallexCustomer($customerId): string
    {
        $customer = $this->customerRepository->getById($customerId);

        $airwallexCustomerIdAttribute = $customer->getCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID);

        if ($airwallexCustomerIdAttribute) {
            $airwallexCustomerId = $airwallexCustomerIdAttribute->getValue();
        } else {
            $airwallexCustomerId = $this->createCustomer
                ->setMagentoCustomerId(self::CUSTOMER_ID_PREFIX . $customerId)
                ->send();

            $this->updateCustomerId($customer, $airwallexCustomerId);
        }

        return $airwallexCustomerId;
    }

    /**
     * @throws InputMismatchException
     * @throws InputException
     * @throws LocalizedException
     */
    protected function updateCustomerId(CustomerInterface $customer, string $airwallexCustomerId)
    {
        $customer->setCustomAttribute(self::KEY_AIRWALLEX_CUSTOMER_ID, $airwallexCustomerId);

        $this->customerRepository->save($customer);
    }
}
