<?php

namespace Airwallex\Payments\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::NOTICE;

    /**
     * File name of the log file
     * @var string
     */
    protected $fileName = '/var/log/airwallex_payments.log';
}
