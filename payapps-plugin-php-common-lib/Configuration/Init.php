<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Configuration;

class Init
{
    /**
     * @var Init|null
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @param array $config
     */
    private function __construct(array $config = [])
    {
        $this->config = $config;
    }

    private function __clone()
    {
    }

    /**
     * @param array $config
     *
     * @return Init
     */
    public static function getInstance(array $config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
