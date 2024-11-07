<?php

namespace Airwallex\Payments\Model\Client\Request;

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

class GetCurrencies extends AbstractClient implements BearerAuthenticationInterface
{
    public function __construct(
        AuthenticationHelper                 $authenticationHelper,
        IdentityService                      $identityService,
        RequestLogger                        $requestLogger,
        Configuration                        $configuration,
        ModuleListInterface                  $moduleList,
        ProductMetadataInterface             $productMetadata,
        CheckoutData                         $checkoutData,
        CacheInterface                       $cache
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
        return 'pa/config/currency_settings';
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     * @return AbstractClient
     */
    public function setPage(int $pageNumber, int $pageSize = 20): AbstractClient
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     * @throws JsonException
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        $response = $this->parseJson($response);

        $result = [];
        foreach ($response->items as $item) {
            if ($item->type !== 'currency_switcher') continue;
            if (empty($item->currencies)) continue;
            $result = array_merge($result, $item->currencies);
        }

        return [
            'has_more' => $response->has_more,
            'items' => $result,
        ];
    }
}
