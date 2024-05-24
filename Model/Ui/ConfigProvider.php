<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit,
 * Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
 * please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Airwallex\Payments\Model\Ui;

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\PaymentConsents;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Block\ReCaptcha;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Airwallex\Payments\Model\Client\Request\RetrieveCustomer;
use Exception;

class ConfigProvider implements ConfigProviderInterface
{
    public const AIRWALLEX_RECAPTCHA_FOR = 'place_order';

    protected Configuration $configuration;
    protected IsCaptchaEnabledInterface $isCaptchaEnabled;
    protected ReCaptcha $reCaptchaBlock;
    protected Session $customerSession;
    protected PaymentConsentsInterface $paymentConsents;
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    private   CustomerRepositoryInterface $customerRepository;
    private   RetrieveCustomer $retrieveCustomer;

    /**
     * ConfigProvider constructor.
     *
     * @param Configuration                 $configuration
     * @param IsCaptchaEnabledInterface     $isCaptchaEnabled
     * @param ReCaptcha                     $reCaptchaBlock
     * @param Session                       $customerSession
     * @param PaymentConsentsInterface      $paymentConsents
     * @param CustomerRepositoryInterface   $customerRepository
     * @param RetrieveCustomer              $retrieveCustomer
     */
    public function __construct(
        Configuration                 $configuration,
        IsCaptchaEnabledInterface     $isCaptchaEnabled,
        ReCaptcha                     $reCaptchaBlock,
        Session                       $customerSession,
        PaymentConsentsInterface      $paymentConsents,
        CustomerRepositoryInterface   $customerRepository,
        RetrieveCustomer              $retrieveCustomer
    ) {
        $this->configuration = $configuration;
        $this->isCaptchaEnabled = $isCaptchaEnabled;
        $this->reCaptchaBlock = $reCaptchaBlock;
        $this->customerSession = $customerSession;
        $this->paymentConsents = $paymentConsents;
        $this->customerRepository = $customerRepository;
        $this->retrieveCustomer = $retrieveCustomer;
    }

    /**
     * Adds mode to checkout config array
     *
     * @return array
     * @throws InputException
     */
    public function getConfig(): array
    {
        $recaptchaEnabled = $this->isReCaptchaEnabled();
        $config = [
            'payment' => [
                'airwallex_payments' => [
                    'mode' => $this->configuration->getMode(),
                    'cc_auto_capture' => $this->configuration->isCardCaptureEnabled(),
                    'recaptcha_enabled' => !!$recaptchaEnabled,
                    'cvc_required' => $this->configuration->isCvcRequired(),
                    'is_card_vault_active' => $this->configuration->isCardVaultActive(),
                ]
            ]
        ];

        if ($recaptchaEnabled) {
            $config['payment']['airwallex_payments']['recaptcha_settings'] = $this->getReCaptchaConfig();
        }

        if ($this->customerSession->isLoggedIn() && $this->configuration->isCardVaultActive()) {
            $config['payment']['airwallex_payments']['airwallex_customer_id'] = $this->getAirwallexCustomerId();
        }

        return $config;
    }


    private function getAirwallexCustomerId(): string {
        $customer = $this->customerRepository->getById($this->customerSession->getId());
        $airwallexCustomerId = $customer->getCustomAttribute(PaymentConsents::KEY_AIRWALLEX_CUSTOMER_ID);

        /**
         * database no airwallex customer id
         *     create
         * database has airwallex customer id
         *     ask for airwallex customer to test if exists
         *           exists
         *                return airwallex customer id
         *           404 
         *                create
         *           other
         *                throw Exception
        */
        if (!$airwallexCustomerId || !$airwallexCustomerId->getValue()) {
            return $this->paymentConsents->createAirwallexCustomer($customer);
        }

        try {
            $this->retrieveCustomer->setAirwallexCustomerId($airwallexCustomerId->getValue())->send();
        } catch (Exception $e) {
            if ($this->retrieveCustomer::NOT_FOUND === $e->getMessage()) {
                return $this->paymentConsents->createAirwallexCustomer($customer);
            }
            throw $e;
        }
        return $airwallexCustomerId->getValue();
    }

    /**
     * Get reCaptcha config
     *
     * @return array
     */
    public function getReCaptchaConfig()
    {
        if (!$this->isReCaptchaEnabled()) {
            return [];
        }

        $this->reCaptchaBlock->setData([
            'recaptcha_for' => self::AIRWALLEX_RECAPTCHA_FOR
        ]);

        return $this->reCaptchaBlock->getCaptchaUiConfig();
    }

    /**
     * Get is reCaptcha enabled
     *
     * @return bool
     */
    public function isReCaptchaEnabled()
    {
        return $this->isCaptchaEnabled->isCaptchaEnabledFor(self::AIRWALLEX_RECAPTCHA_FOR);
    }
}
