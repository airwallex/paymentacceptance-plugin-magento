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
Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Airwallex\Payments\Model\Config;

use Airwallex\Payments\Api\ServiceInterface;
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
    const PROTECTED_METHODS = [
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
        IsCaptchaEnabledInterface $isEnabled,
        ValidationConfigResolverInterface $configResolver,
        RestRequest $request
    ) {
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
        if ($endpoint->getServiceClass() === ServiceInterface::class
            && array_key_exists($endpoint->getServiceMethod(), self::PROTECTED_METHODS)) {
            if ($this->isEnabled->isCaptchaEnabledFor(ConfigProvider::AIRWALLEX_RECAPTCHA_FOR)) {
                return $this->configResolver->get(ConfigProvider::AIRWALLEX_RECAPTCHA_FOR);
            }
        }

        return null;
    }
}
