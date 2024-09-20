<?php

namespace Airwallex\Payments\Model\Config;

use Airwallex\Payments\Api\OrderServiceInterface;
use Airwallex\Payments\Model\Ui\ConfigProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;

class WebapiConfigProvider implements WebapiValidationConfigProviderInterface
{
    public const PROTECTED_METHODS = [
        'airwallexGuestPlaceOrder' => true,
        'airwallexPlaceOrder' => true
    ];
    protected IsCaptchaEnabledInterface $isEnabled;
    protected ValidationConfigResolverInterface $configResolver;
    protected RestRequest $request;

    /**
     * @param IsCaptchaEnabledInterface $isEnabled
     * @param ValidationConfigResolverInterface $configResolver
     * @param RestRequest $request
     */
    public function __construct(
        IsCaptchaEnabledInterface         $isEnabled,
        ValidationConfigResolverInterface $configResolver,
        RestRequest                       $request
    )
    {
        $this->isEnabled = $isEnabled;
        $this->configResolver = $configResolver;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     * @throws InputException
     */
    public function getConfigFor(EndpointInterface $endpoint): ?ValidationConfigInterface
    {
        if ($endpoint->getServiceClass() === OrderServiceInterface::class
            && array_key_exists($endpoint->getServiceMethod(), self::PROTECTED_METHODS)) {
            if ($this->isEnabled->isCaptchaEnabledFor(ConfigProvider::AIRWALLEX_RECAPTCHA_FOR)) {
                return $this->configResolver->get(ConfigProvider::AIRWALLEX_RECAPTCHA_FOR);
            }
        }

        return null;
    }
}
