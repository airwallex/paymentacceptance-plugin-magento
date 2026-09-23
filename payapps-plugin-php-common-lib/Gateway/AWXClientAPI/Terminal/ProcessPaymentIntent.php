<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Terminal;

use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Terminal as StructTerminal;

class ProcessPaymentIntent extends AbstractApi
{
    const ERROR_VALIDATION_ERROR = 'validation_error';
    const ERROR_INVALID_STATUS_FOR_OPERATION = 'invalid_status_for_operation';
    const ERROR_TERMINAL_BUSY = 'terminal_busy';
    const ERROR_TERMINAL_UNAVAILABLE = 'terminal_unavailable';
    const ERROR_UNAUTHORIZED = 'unauthorized';
    const ERROR_RESOURCE_NOT_FOUND = 'resource_not_found';

    /**
     * @var string
     */
    private $id;

    /**
     * @inheritDoc
     */
    protected function getUri(): string
    {
        return 'pa/pos/terminals/' . $this->id . '/process_payment_intent';
    }

    /**
     * @param string $terminalId
     * @return ProcessPaymentIntent
     */
    public function setTerminalId(string $terminalId): ProcessPaymentIntent
    {
        $this->id = $terminalId;
        return $this;
    }

    /**
     * @param string $paymentIntentId
     * @return ProcessPaymentIntent
     */
    public function setPaymentIntentId(string $paymentIntentId): ProcessPaymentIntent
    {
        return $this->setParam('payment_intent_id', $paymentIntentId);
    }

    /**
     * @param array $paymentMethodOptions
     * @return ProcessPaymentIntent
     */
    public function setPaymentMethodOptions(array $paymentMethodOptions): ProcessPaymentIntent
    {
        return $this->setParam('payment_method_options', $paymentMethodOptions);
    }

    /**
     * @param $response
     * @return StructTerminal
     */
    protected function parseResponse($response): StructTerminal
    {
        return new StructTerminal(json_decode((string)$response->getBody(), true));
    }
}