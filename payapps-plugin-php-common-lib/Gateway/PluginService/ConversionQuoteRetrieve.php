<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\PluginService;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\ConversionQuote as StructConversionQuote;

class ConversionQuoteRetrieve extends AbstractApi
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
        return 'pa/conversion_quotes/' . $this->id;
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
     * @return ConversionQuoteRetrieve
     */
    public function setConversionQuoteId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param $response
     *
     * @return StructConversionQuote
     */
    protected function parseResponse($response): StructConversionQuote
    {
        return new StructConversionQuote(json_decode((string)$response->getBody(), true));
    }
}
