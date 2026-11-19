<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Logger;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Init;

class FileLogger
{
    const ALLOWED_LEVELS = ['info', 'error', 'warning', 'debug'];

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

    public function getLogFilePath(string $level, string $date = ''): string
    {
        $init = Init::getInstance();
        $logDir = $this->getLogDirectory();
        if ($logDir === '') {
            return '';
        }
        $date = $date !== '' ? $date : date('Y-m-d');
        $level = strtolower($level);
        if (!in_array($level, self::ALLOWED_LEVELS, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return '';
        }
        $secret = implode('|', array_map('base64_encode', [
            (string) $init->get('api_key'),
            (string) $init->get('client_id'),
            $logDir,
            (string) $this->suppressWarnings(static function () {
                return fileinode(__DIR__);
            }),
        ]));
        $hash = substr(hash_hmac('sha256', $level . '|' . $date, $secret), 0, 16);

        return $logDir . DIRECTORY_SEPARATOR . sprintf('airwallex_%s_%s_%s.log', $level, $date, $hash);
    }

    protected function writeLog($level, $message, array $context = [])
    {
        try {
            $level = strtolower((string) $level);
            if (!in_array($level, self::ALLOWED_LEVELS, true)) {
                return;
            }

            $logDir = $this->getLogDirectory();
            if ($logDir === '' || !$this->suppressWarnings(static function () use ($logDir) {
                return is_dir($logDir) && is_writable($logDir);
            })) {
                return;
            }

            $filePath = $this->getLogFilePath($level);
            if ($this->suppressWarnings(static function () use ($filePath) {
                return is_link($filePath);
            })) {
                return;
            }

            $time = date('Y-m-d H:i:s');

            $extra = '';
            if (!empty($context)) {
                $extra = ' ' . json_encode(Redactor::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $line = sprintf("[%s] %s: %s%s\n", $time, strtoupper($level), Redactor::redact((string) $message), $extra);

            $this->suppressWarnings(static function () use ($filePath, $line) {
                return file_put_contents($filePath, $line, FILE_APPEND);
            });
        } catch (\Throwable $e) {
            // Logging failures must not interrupt payment processing.
        }
    }

    private function suppressWarnings(callable $callback)
    {
        set_error_handler(static function () {
            return true;
        });
        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function getLogDirectory(): string
    {
        return rtrim((string) Init::getInstance()->get('log_dir'), '/\\');
    }
}
