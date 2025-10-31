<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Terminal;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Terminal;

class Retrieve extends AbstractApi
{
    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/pos/terminals/' . $this->id;
    }

    /**
     * @inheritDoc
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * @param string $id
     *
     * @return Retrieve
     */
    public function setTerminalId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param $response
     *
     * @return Terminal
     */
    protected function parseResponse($response): Terminal
    {
        return new Terminal(json_decode((string)$response->getBody(), true));
    }
}
