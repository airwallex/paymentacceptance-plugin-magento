<?php

namespace Airwallex\Payments\Model\Ui;

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Helper\Configuration;
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
                    'card_max_width' => $this->configuration->getCardMaxWidth(),
                    'card_fontsize' => $this->configuration->getCardFontSize(),
                ]
            ]
        ];

        if ($recaptchaEnabled) {
            $config['payment']['airwallex_payments']['recaptcha_settings'] = $this->getReCaptchaConfig();
        }

        if ($this->customerSession->isLoggedIn() && $this->configuration->isCardVaultActive()) {
            $config['payment']['airwallex_payments']['airwallex_customer_id'] = $this->getAirwallexCustomerId();
            $this->paymentConsents->syncVault($this->customerSession->getId());
        }

        return $config;
    }


    private function getAirwallexCustomerId(): string {
        $customer = $this->customerRepository->getById($this->customerSession->getId());
        $airwallexCustomerId = $this->paymentConsents->getAirwallexCustomerIdInDB($this->customerSession->getId());
        /**
         * database has no airwallex customer id
         *     create
         * database has airwallex customer id
         *     throw exception
         *         404 
         *             create
         *         other
         *             throw Exception
         *     return airwallex customer id
        */
        if (!$airwallexCustomerId) {
            return $this->paymentConsents->createAirwallexCustomer($customer);
        }

        try {
            $this->retrieveCustomer->setAirwallexCustomerId($airwallexCustomerId)->send();
        } catch (Exception $e) {
            if ($this->retrieveCustomer::NOT_FOUND === $e->getMessage()) {
                return $this->paymentConsents->createAirwallexCustomer($customer);
            }
            return '';
        }
        return $airwallexCustomerId;
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
