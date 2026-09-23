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
namespace Airwallex\Payments\Controller\Webhooks;

use Airwallex\Payments\CommonLibraryInit;
use Airwallex\Payments\Exception\WebhookException;
use Psr\Log\LoggerInterface as Logger;
use Airwallex\Payments\Model\Webhook\Webhook;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService\Log as RemoteLog;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const JSON_DECODE_DEPTH = 512;
    private const HTTP_OK = 200;
    private const HTTP_UNAUTHORIZED = 401;

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
     * @var UrlInterface
     */
    private UrlInterface $urlInterface;

    /**
     * Index constructor.
     *
     * @param Webhook $webhook
     * @param RequestHttp $request
     * @param ResponseHttp $response
     * @param Logger $logger
     * @param UrlInterface $urlInterface
     * @param CommonLibraryInit $commonLibraryInit
     */
    public function __construct(
        Webhook $webhook,
        RequestHttp $request,
        ResponseHttp $response,
        Logger $logger,
        UrlInterface $urlInterface,
        CommonLibraryInit $commonLibraryInit
    ) {
        $this->webhook = $webhook;
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        $this->urlInterface = $urlInterface;
        $commonLibraryInit->exec();
    }

    /**
     * Get request from airwallex. Check request checksum for security. Each request parse and run webhook action
     *
     * @return ResponseHttp
     * @throws WebhookException
     * @throws GuzzleException
     * @throws JsonException
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(): ResponseHttp
    {
        $data = json_decode($this->request->getContent(), false, self::JSON_DECODE_DEPTH, JSON_THROW_ON_ERROR);
        $returnUrl = $data->data->object->return_url ?? '';
        $baseUrl = $this->urlInterface->getUrl();

        if ($returnUrl && substr($returnUrl, 0, strlen($baseUrl)) !== $baseUrl) {
            return $this->response->setStatusCode(self::HTTP_OK);
        }
        try {
            $this->webhook->checkChecksum($this->request);
        } catch (Exception $e) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $webhookHost = $data->data->object->metadata->host ?? '';
            if ($host && $webhookHost && $host !== $webhookHost) {
                return $this->response->setStatusCode(self::HTTP_UNAUTHORIZED);
            }
            throw $e;
        }
        try {
            if (isset($data->name) && isset($data->data->object)) {
                $this->webhook->dispatch($data->name, $data->data->object);
            }
        } catch (Exception $e) {
            $id = $data->sourceId ?? '';
            $name = $data->name;
            RemoteLog::error("$name $id webhook: " . $e->getMessage(), RemoteLog::ON_PROCESS_WEBHOOK_ERROR);
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
