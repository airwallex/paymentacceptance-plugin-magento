<?php

namespace Airwallex\Payments\Model\Traits;

use Magento\Quote\Api\CartManagementInterface;

trait HelperTrait
{
    public function convertToDisplayCurrency(float $amount, $rate, $reverse = false) : float
    {
        if (empty($rate)) {
            return $amount;
        }
        if ($reverse) {
            $ret = round(floatval($amount / $rate), 4);
        } else {
            $ret = round(floatval($amount * $rate), 4);
        }
        return is_numeric($ret) ? $ret : 0;
    }

    public function convertCcType(string $type) : string {
        if (strtolower($type) === 'jcb') {
            return 'jcb';
        }
        if (strtolower($type) === 'visa') {
            return 'vi';
        }
        if (strtolower($type) === 'discover') {
            return 'di';
        }
        if (in_array(strtolower($type), ['diners', 'diners club international'])) {
                return 'dn';
        }
        if (in_array(strtolower($type), ['amex', 'american express', 'americanexpress'])) {
            return 'ae';
        }
        if (in_array(strtolower($type), ['unionpay', 'up', 'union pay'])) {
            return 'un';
        }
        if (in_array(strtolower($type), ['mastercard', 'master card'])) {
            return 'mc';
        }
        return strtolower($type);
    }

    public function isAmountEqual(float $a, float $b) {
        return abs($a - $b) <= 0.01;
    }

    public function isRedirectMethodConstant($string)
    {
        $reflectionClass = new \ReflectionClass('Airwallex\Payments\Model\Methods\RedirectMethod');
        $constants = $reflectionClass->getConstants();

        return in_array($string, $constants);
    }

    public function checkIntentWithQuote(
        string $status, 
        string $intentCurrency, 
        string $quoteCurrency, 
        string $intentOrderId, 
        string $quoteOrderId, 
        float $intentAmount,
        float $quoteAmount
    ) {
        if (
            !in_array($status, ['SUCCEEDED', 'REQUIRES_CAPTURE'], true)
            || $intentCurrency !== $quoteCurrency 
            || $intentOrderId !== $quoteOrderId 
            || !$this->isAmountEqual($intentAmount, $quoteAmount)) {
            $this->errorLog->setMessage('check intent failed', "Intent Order ID: {$intentOrderId} - Quote Order ID: {$quoteOrderId} - " 
            . "Intent Currency: {$intentCurrency} - Quote Currency: {$quoteCurrency} - " 
            . "Intent Amount: {$intentAmount} - Quote Amount: {$quoteAmount}", $intentOrderId)->send();
            $msg = 'Something went wrong while processing your request.';
            throw new \Exception(__($msg));
        }
    }

    public function placeOrderByQuoteId(int $quoteId, $from = 'service')
    {
        $lockKey = 'airwallex_place_order_' . (string)$quoteId;
        if ($this->cache->load($lockKey)) {
            $message = 'Could not obtain lock';
            throw new \Exception($message);
        }
        $this->cache->save('locked', $lockKey, [], 10);
        try {
            $quote = $this->quoteRepository->get($quoteId);
            if (!$quote->getCustomerId()) {
                $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
            }
            try {
                $order = $this->order->loadByAttribute('quote_id', $quoteId);
                $orderId = $order->getId();
            } catch (\Exception $e){};
            if (empty($order) || empty($order->getEntityId())) {
                if ($from !== 'service') {
                    $quote->setTotalsCollectedFlag(true);                
                }
                $orderId = $this->cartManagement->placeOrder($quoteId);
            }
        } finally {
            $this->cache->remove($lockKey);
        }
        return $orderId;
    }
}
