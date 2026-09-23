<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Struct;

class Response extends AbstractBase
{
    /**
     * @var string
     */
    private $body;

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body ?? '';
    }

    /**
     * @param string $body
     *
     * @return Response
     */
    public function setBody(string $body): Response
    {
        $this->body = $body;
        return $this;
    }
}
