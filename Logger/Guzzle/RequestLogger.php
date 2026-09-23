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
namespace Airwallex\Payments\Logger\Guzzle;

use Airwallex\Payments\Helper\Configuration;
use Monolog\Logger;
use Exception;
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
