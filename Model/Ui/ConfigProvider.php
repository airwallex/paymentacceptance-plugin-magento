<?php

namespace Airwallex\Payments\Model\Ui;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Afterpay;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Klarna;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\BankTransfer;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Client\Request\GetCurrencies;
use Airwallex\Payments\Model\Methods\RedirectMethod;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\RedirectMethod as RedirectMethodConfiguration;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Error;
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
use Magento\Checkout\Helper\Data as CheckoutData;
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
    private CheckoutData $checkoutData;

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
     * @param CheckoutData $checkoutData
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
        CacheInterface              $cache,
        CheckoutData                $checkoutData
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
        $this->checkoutData = $checkoutData;
    }

    /**
     * Adds mode to check out config array
     *
     * @return array
     * @throws GuzzleException
     */
    public function getConfig(): array
    {
        try {
            $quote = $this->checkoutData->getQuote();
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
                        'bank_transfer_support_country_to_currency_collection' => BankTransfer::SUPPORTED_COUNTRY_TO_CURRENCY,
                        'klarna_support_countries' => Klarna::SUPPORTED_COUNTRY_TO_CURRENCY,
                        'afterpay_support_countries' => Afterpay::SUPPORTED_COUNTRY_TO_CURRENCY,
                        'afterpay_support_entity_to_currency' => Afterpay::SUPPORTED_ENTITY_TO_CURRENCIES,
                        'available_currencies' => $this->getAvailableCurrencies(),
                        'quote_currency_code' => $quote->getQuoteCurrencyCode(),
                        'base_currency_code' => $quote->getBaseCurrencyCode(),
                        'redirect_method_default_currency' => RedirectMethodConfiguration::DEFAULT_CURRENCY,
                        'redirect_method_country_to_currency' => RedirectMethodConfiguration::SUPPORTED_COUNTRY_TO_CURRENCY,
                        'redirect_method_entity_to_currency' => RedirectMethodConfiguration::SUPPORTED_ENTITY_TO_CURRENCY,
                        'redirect_method_display_names' => RedirectMethod::displayNames(),
                    ]
                ]
            ];

            if ($this->customerSession->isLoggedIn() && $this->configuration->isCardVaultActive()) {
                $config['payment']['airwallex_payments']['airwallex_customer_id'] = $this->getAirwallexCustomerId();
            }
            return $config;
        } catch (Exception|Error $exception) {
            return [];
        }
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

