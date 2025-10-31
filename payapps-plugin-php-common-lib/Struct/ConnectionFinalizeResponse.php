<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class ConnectionFinalizeResponse extends AbstractBase
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var int
     */
    private $status;

    /**
     * @var float
     */
    private $time;

    /**
     * @var string
     */
    private $requestData;

    /**
     * @var string
     */
    private $requestUrl;

    /**
     * @var string
     */
    private $error;

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * @param array $data
     *
     * @return ConnectionFinalizeResponse
     */
    public function setData(array $data): ConnectionFinalizeResponse
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status ?? 0;
    }

    /**
     * @param int $status
     *
     * @return ConnectionFinalizeResponse
     */
    public function setStatus(int $status): ConnectionFinalizeResponse
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return float
     */
    public function getTime(): float
    {
        return $this->time ?? 0.0;
    }

    /**
     * @param float $time
     *
     * @return ConnectionFinalizeResponse
     */
    public function setTime(float $time): ConnectionFinalizeResponse
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestData(): string
    {
        return $this->requestData ?? '';
    }

    /**
     * @param string $requestData
     *
     * @return ConnectionFinalizeResponse
     */
    public function setRequestData(string $requestData): ConnectionFinalizeResponse
    {
        $this->requestData = $requestData;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->requestUrl ?? '';
    }

    /**
     * @param string $requestUrl
     *
     * @return ConnectionFinalizeResponse
     */
    public function setRequestUrl(string $requestUrl): ConnectionFinalizeResponse
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error ?? '';
    }

    /**
     * @param string $error
     *
     * @return ConnectionFinalizeResponse
     */
    public function setError(string $error): ConnectionFinalizeResponse
    {
        $this->error = $error;
        return $this;
    }
}
