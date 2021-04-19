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
namespace Airwallex\Payments\Logger\Guzzle;

use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Logger;
use GuzzleHttp\HandlerStack;
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
     * @throws \Exception
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
