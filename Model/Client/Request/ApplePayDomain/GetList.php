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
namespace Airwallex\Payments\Model\Client\Request\ApplePayDomain;

use Airwallex\Payments\Helper\AuthenticationHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Guzzle\RequestLogger;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use JsonException;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Http\Message\ResponseInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Checkout\Helper\Data as CheckoutData;

class GetList extends AbstractClient implements BearerAuthenticationInterface
{
    public function __construct(
        AuthenticationHelper     $authenticationHelper,
        IdentityService          $identityService,
        RequestLogger            $requestLogger,
        Configuration            $configuration,
        ModuleListInterface      $moduleList,
        ProductMetadataInterface $productMetadata,
        CheckoutData             $checkoutData,
        CacheInterface           $cache
    )
    {
        parent::__construct($authenticationHelper, $identityService, $requestLogger, $configuration, $productMetadata, $moduleList, $checkoutData, $cache);
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/config/applepay/registered_domains';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        return $this->parseJson($response)->items ?? [];
    }
}
