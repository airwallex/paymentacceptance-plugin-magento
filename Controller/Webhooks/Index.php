<?php

namespace Airwallex\Payments\Controller\Webhooks;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Logger\Logger;
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
use Airwallex\Payments\Model\Client\Request\Log as RequestLog;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

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
     * @param RequestLog $requestLog
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

        $this->webhook->checkChecksum($this->request);
        try {
            if (isset($data->name) && isset($data->data->object)) {
                $this->webhook->dispatch($data->name, $data->data->object);
            }
        } catch (Exception $e) {
            $id = $data->sourceId ?? '';
            $this->requestLog->setMessage($data->name . " $id webhook: " . $e->getMessage(), $e->getTraceAsString())->send();
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
