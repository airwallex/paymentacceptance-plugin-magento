<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Model\Ui;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Afterpay;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\Klarna;
use Airwallex\PayappsPlugin\CommonLibrary\Configuration\PaymentMethodType\BankTransfer;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\Payments\Api\PaymentConsentsInterface;
use Airwallex\Payments\Helper\Configuration;
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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\ReCaptchaUi\Block\ReCaptcha;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Customer\Retrieve as RetrieveCustomer;
use Magento\Checkout\Helper\Data as CheckoutData;
use Exception;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\CommonLibraryInit;

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
    private AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    private ScopeConfigInterface $scopeConfig;

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
     * @param CacheInterface $cache
     * @param CheckoutData $checkoutData
     * @param AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param CommonLibraryInit $commonLibraryInit
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
        CacheInterface              $cache,
        CheckoutData                $checkoutData,
        ScopeConfigInterface        $scopeConfig,
        CommonLibraryInit           $commonLibraryInit,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper
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
        $this->cache = $cache;
        $this->checkoutData = $checkoutData;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->scopeConfig = $scopeConfig;

        $commonLibraryInit->exec();
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
                        'is_order_before_payment' => $this->configuration->isOrderBeforePayment(),
                        'apm_selected_logos' => $this->getSelectedPaymentMethodLogos(),
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
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
            $this->retrieveCustomer->setCustomerId($airwallexCustomerId)->send();
        } catch (RequestException $e) {
            $error = json_decode($e->getMessage(), true);
            $code = (is_array($error) && isset($error['code'])) ? $error['code'] : '';
            if ($code === AbstractApi::ERROR_RESOURCE_NOT_FOUND) {
                return $this->paymentConsents->createAirwallexCustomer($customer);
            }
            $this->logError(__METHOD__ . ': ' . $e->getMessage());
            return '';
        } catch (Exception $exception) {
            $this->logError(__METHOD__ . ': ' . $exception->getMessage());
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

    private function getSelectedPaymentMethodLogos(): array
    {
        $configuredLogos = $this->scopeConfig->getValue('payment/airwallex_payments_apm/selected_payment_method_logos');

        if (empty($configuredLogos)) {
            return [];
        }

        try {
            $allMethods = $this->availablePaymentMethodsHelper->getAllPaymentMethodTypes();
            $selectedLogoNames = explode(',', $configuredLogos);
            $selectedLogos = [];
            foreach ($allMethods as $method) {
                $methodName = $method->getName();
                if (!in_array($methodName, $selectedLogoNames)) {
                    continue;
                }

                $resources = $method->getResources();
                $logos = $resources['logos'] ?? [];

                if (!empty($logos['svg'])) {
                    $selectedLogos[] = $logos['svg'];
                    continue;
                }

                if (!empty($logos['png'])) {
                    $selectedLogos[] = $logos['png'];
                }
            }
            return array_slice($selectedLogos, 0, 5);
        } catch (Exception $e) {
            $this->logError('[ConfigProvider] Failed to get selected payment method logos: ' . $e->getMessage());
            return [];
        }
    }
}

