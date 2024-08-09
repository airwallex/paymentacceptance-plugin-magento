<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Airwallex\Payments\Controller\Webhooks;

use Airwallex\Payments\Logger\Logger;
use Airwallex\Payments\Model\Webhook\Webhook;
use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Airwallex\Payments\Model\Client\Request\Log as RequestLog;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const JSON_DECODE_DEPTH = 512;
    private const HTTP_OK = 200;

    /**
     * @var Webhook
     */
    private Webhook $webhook;
    /**
     * @var RequestHttp
     */
    private RequestHttp $request;

    /**
     * @var ResponseHttp
     */
    private ResponseHttp $response;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var RequestLog
     */
    private RequestLog $requestLog;

    /**
     * Index constructor.
     *
     * @param Webhook $webhook
     * @param RequestHttp $request
     * @param ResponseHttp $response
     * @param Logger $logger
     */
    public function __construct(
        Webhook $webhook,
        RequestHttp $request,
        ResponseHttp $response,
        Logger $logger,
        RequestLog $requestLog
    ) {
        $this->webhook = $webhook;
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        $this->requestLog = $requestLog;
    }

    /**
     * Get request from airwallex. Check request checksum for security. Each request parse and run webhook action
     *
     * @return ResponseHttp
     * @throws \JsonException
     */
    public function execute()
    {
        $data = json_decode($this->request->getContent(), false, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);

        $this->webhook->checkChecksum($this->request);
        try {
            if ($data->data) {
                $this->webhook->dispatch($data->name, $data->data->object);
            }
        } catch (Exception $e) {
            $this->requestLog->setMessage($e->getMessage(), $e->getTraceAsString())->send();
            $this->logger->error($e->getMessage());
            throw $e;
        }

        return $this->response->setStatusCode(self::HTTP_OK);
    }

    /**
     * @param RequestInterface $request
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
