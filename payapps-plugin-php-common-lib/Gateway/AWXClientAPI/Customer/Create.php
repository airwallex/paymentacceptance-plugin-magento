<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\DataSanitizationTrait;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Customer;
use Airwallex\PayappsPlugin\CommonLibrary\Util\StringHelper;
use Exception;

class Create extends AbstractApi
{
    use DataSanitizationTrait;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/customers/create';
    }

    /**
     * @param int|string $platformUserId
     * @return Create
     *
     * @throws Exception
     */
    public function setCustomerId($platformUserId): Create
    {
        $platform = Init::getInstance()->get('plugin_type', '');
        $merchantCustomerId = substr(bin2hex(random_bytes(10)), 0, 20);
        return $this->setParam('merchant_customer_id', $platform . '-' . $platformUserId . '-' . $merchantCustomerId);
    }

    /**
     * @param string $email
     * @return Create
     */
    public function setEmail(string $email): Create
    {
        return $this->setParam('email', StringHelper::sanitize($email, 256));
    }

    /**
     * @param string $firstName
     * @return Create
     */
    public function setFirstName(string $firstName): Create
    {
        return $this->setParam('first_name', StringHelper::sanitize($firstName, 128));
    }

    /**
     * @param string $lastName
     * @return Create
     */
    public function setLastName(string $lastName): Create
    {
        return $this->setParam('last_name', StringHelper::sanitize($lastName, 128));
    }

    /**
     * @param string $phoneNumber
     * @return Create
     */
    public function setPhoneNumber(string $phoneNumber): Create
    {
        return $this->setParam('phone_number', StringHelper::sanitize($phoneNumber, 50, false));
    }

    /**
     * @param string $businessName
     * @return Create
     */
    public function setBusinessName(string $businessName): Create
    {
        return $this->setParam('business_name', StringHelper::sanitize($businessName, 128));
    }

    /**
     * @param array $address
     * @return Create
     */
    public function setAddress(array $address): Create
    {
        return $this->setParam('address', $this->sanitizeAddressData($address));
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