<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Customer;

class Update extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @param string $customerId
     *
     * @return Update
     */
    public function setCustomerId(string $customerId): Update
    {
        $this->id = $customerId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/customers/' . $this->id . '/update';
    }

    /**
     * @param array $address
     *
     * @return Update
     */
    public function setAddress(array $address): Update
    {
        return $this->setParam('address', $address);
    }

    /**
     * @param string $businessName
     *
     * @return Update
     */
    public function setBusinessName(string $businessName): Update
    {
        return $this->setParam('business_name', $businessName);
    }

    /**
     * @param string $email
     *
     * @return Update
     */
    public function setEmail(string $email): Update
    {
        return $this->setParam('email', $email);
    }

    /**
     * @param string $firstName
     *
     * @return Update
     */
    public function setFirstName(string $firstName): Update
    {
        return $this->setParam('first_name', $firstName);
    }

    /**
     * @param string $lastName
     *
     * @return Update
     */
    public function setLastName(string $lastName): Update
    {
        return $this->setParam('last_name', $lastName);
    }

    /**
     * @param string $phoneNumber
     *
     * @return Update
     */
    public function setPhoneNumber(string $phoneNumber): Update
    {
        return $this->setParam('phone_number', $phoneNumber);
    }

    /**
     * @param string $merchantCustomerId
     *
     * @return Update
     */
    public function setMerchantCustomerId(string $merchantCustomerId): Update
    {
        return $this->setParam('merchant_customer_id', $merchantCustomerId);
    }

    /**
     * @param $response
     *
     * @return Customer
     */
    protected function parseResponse($response): Customer
    {
        return new Customer(json_decode((string)$response->getBody(), true));
    }
}
