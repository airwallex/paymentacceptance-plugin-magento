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
