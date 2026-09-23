<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Logger;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;

class FileLogger
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new FileLogger();
        }
        return self::$instance;
    }

    public function logInfo($message, array $context = [])
    {
        $this->writeLog('info', $message, $context);
    }

    public function logError($message, array $context = [])
    {
        $this->writeLog('error', $message, $context);
    }

    public function logWarning($message, array $context = [])
    {
        $this->writeLog('warning', $message, $context);
    }

    public function logDebug($message, array $context = [])
    {
        $this->writeLog('debug', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::getInstance()->logInfo($message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::getInstance()->logError($message, $context);
    }

    public static function warning($message, array $context = [])
    {
        self::getInstance()->logWarning($message, $context);
    }

    public static function debug($message, array $context = [])
    {
        self::getInstance()->logDebug($message, $context);
    }

    protected function writeLog($level, $message, array $context = [])
    {
        $logDir = Init::getInstance()->get('log_dir');

        if (empty($logDir) || !is_dir($logDir) || !is_writable($logDir)) {
            return;
        }

        $date = date('Y-m-d');
        $filename = sprintf('airwallex_%s_%s.log', $level, $date);
        $filePath = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        
        $time = date('Y-m-d H:i:s');

        $extra = '';
        if (!empty($context)) {
            $extra = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = sprintf("[%s] %s: %s%s\n", $time, strtoupper($level), $message, $extra);

        file_put_contents($filePath, $line, FILE_APPEND);
    }
}
