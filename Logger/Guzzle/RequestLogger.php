<?php

namespace Airwallex\Payments\Logger\Guzzle;

use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Logger;
use Exception;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Monolog\Handler\StreamHandler;

class RequestLogger
{
    private const FILE_NAME = 'airwallex_request.log';

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * RequestLogger constructor.
     *
     * @param Configuration $configuration
     * @param DirectoryList $directoryList
     * @param Logger $logger
     */
    public function __construct(Configuration $configuration, DirectoryList $directoryList, Logger $logger)
    {
        $this->configuration = $configuration;
        $this->directoryList = $directoryList;
        $this->logger = $logger;
    }

    /**
     * @return HandlerStack
     * @throws Exception
     */
    public function getStack(): HandlerStack
    {
        $stack = HandlerStack::create();

        if (!$this->configuration->isRequestLoggerEnable()) {
            return $stack;
        }

        $stack->push(Middleware::log(
            (new Logger('Logger'))->pushHandler(new StreamHandler($this->getFileLocation())),
            new MessageFormatter(MessageFormatter::DEBUG)
        ));

        return $stack;
    }

    /**
     * @return string
     */
    private function getFileLocation(): string
    {
        try {
            return $this->directoryList->getPath(DirectoryList::LOG) . DIRECTORY_SEPARATOR . self::FILE_NAME;
        } catch (FileSystemException $exception) {
            $this->logger->error($exception->getMessage());
        }

        return '';
    }
}
