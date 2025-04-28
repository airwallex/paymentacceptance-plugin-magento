<?php

namespace Airwallex\Payments\Model\Ui;

use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Client\Request\GetCurrencies;
use Airwallex\Payments\Model\Methods\KlarnaMethod;
use Airwallex\Payments\Model\Traits\HelperTrait;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ReCaptchaUi\Block\ReCaptcha;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Airwallex\Payments\Model\Client\Request\RetrieveCustomer;
use Airwallex\Payments\Model\Methods\AfterpayMethod;
use Exception;

class ConfigProvider implements ConfigProviderInterface
{
    use HelperTrait;
    public const AIRWALLEX_RECAPTCHA_FOR = 'place_order';

    protected Configuration $configuration;
    protected IsCaptchaEnabledInterface $isCaptchaEnabled;
    protected ReCaptcha $reCaptchaBlock;
    protected Session $customerSession;
    protected PaymentConsentsInterface $paymentConsents;
    private CustomerRepositoryInterface $customerRepository;
    private RetrieveCustomer $retrieveCustomer;
    private ProductMetadata $productMetadata;
    private GetCurrencies $getCurrencies;

    private CacheInterface $cache;

    /**
     * ConfigProvider constructor.
     *
     * @param Configuration $configuration
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param ReCaptcha $reCaptchaBlock
     * @param Session $customerSession
     * @param PaymentConsentsInterface $paymentConsents
     * @param CustomerRepositoryInterface $customerRepository
     * @param RetrieveCustomer $retrieveCustomer
     * @param ProductMetadata $productMetadata
     * @param GetCurrencies $getCurrencies
     * @param CacheInterface $cache
     */
    public function __construct(
        Configuration               $configuration,
        IsCaptchaEnabledInterface   $isCaptchaEnabled,
        ReCaptcha                   $reCaptchaBlock,
        Session                     $customerSession,
        PaymentConsentsInterface    $paymentConsents,
        CustomerRepositoryInterface $customerRepository,
        RetrieveCustomer            $retrieveCustomer,
        ProductMetadata             $productMetadata,
        GetCurrencies               $getCurrencies,
        CacheInterface              $cache
    )
    {
        $this->configuration = $configuration;
        $this->isCaptchaEnabled = $isCaptchaEnabled;
        $this->reCaptchaBlock = $reCaptchaBlock;
        $this->customerSession = $customerSession;
        $this->paymentConsents = $paymentConsents;
        $this->customerRepository = $customerRepository;
        $this->retrieveCustomer = $retrieveCustomer;
        $this->productMetadata = $productMetadata;
        $this->getCurrencies = $getCurrencies;
        $this->cache = $cache;
    }

    /**
     * Adds mode to check out config array
     *
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        $config = [
            'payment' => [
                'airwallex_payments' => [
                    'mode' => $this->configuration->getMode(),
                    'cc_auto_capture' => $this->configuration->isAutoCapture('card'),
                    'is_recaptcha_enabled' => $this->isReCaptchaEnabled(),
                    'recaptcha_type' => $this->configuration->recaptchaType(),
                    'recaptcha_settings' => $this->getReCaptchaConfig(),
                    'is_recaptcha_shared' => version_compare($this->productMetadata->getVersion(), '2.4.7', '<'),
                    'cvc_required' => $this->configuration->isCvcRequired(),
                    'is_card_vault_active' => $this->configuration->isCardVaultActive(),
                    'is_pre_verification_enabled' => $this->configuration->isPreVerificationEnabled(),
                    'card_max_width' => $this->configuration->getCardMaxWidth(),
                    'card_fontsize' => $this->configuration->getCardFontSize(),
                    'klarna_support_countries' => KlarnaMethod::SUPPORTED_COUNTRY_TO_CURRENCY,
                    'afterpay_support_countries' => AfterpayMethod::SUPPORTED_COUNTRY_TO_CURRENCY,
                    'afterpay_support_entity_to_currency' => AfterpayMethod::SUPPORTED_ENTITY_TO_CURRENCY,
                    'available_currencies' => $this->getAvailableCurrencies(),
                ]
            ]
        ];

        if ($this->customerSession->isLoggedIn() && $this->configuration->isCardVaultActive()) {
            $config['payment']['airwallex_payments']['airwallex_customer_id'] = $this->getAirwallexCustomerId();
            $this->paymentConsents->syncVault($this->customerSession->getId());
        }

        return $config;
    }


    /**
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws LocalizedException
     */
    private function getAirwallexCustomerId(): string
    {
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
     * @throws InputException
     */
    public function getReCaptchaConfig(): array
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
     * @throws InputException
     */
    public function isReCaptchaEnabled(): bool
    {
        return $this->isCaptchaEnabled->isCaptchaEnabledFor(self::AIRWALLEX_RECAPTCHA_FOR);
    }
}

