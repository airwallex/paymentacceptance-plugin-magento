<?php

namespace Airwallex\Payments\Model\Traits;

use Airwallex\Payments\Api\Data\PaymentIntentInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use ReflectionClass;

trait HelperTrait
{
    public function convertToDisplayCurrency(float $amount, $rate, $reverse = false): float
    {
        if (empty($rate)) {
            return $amount;
        }
        if ($reverse) {
            return round($amount / $rate, 4);
        }
        return round($amount * $rate, 4);
    }

    public function getBaseAmount(float $amount, float $rate, float $amountMax, float $baseAmountMax): float
    {
        $baseAmount = $this->convertToDisplayCurrency($amount, $rate, true);
        if ($this->isAmountEqual($amount, $amountMax) || $baseAmount - $baseAmountMax >= 0.0001) {
            $baseAmount = $baseAmountMax;
        }
        return $baseAmount;
    }

    public function convertCcType(string $type): string
    {
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

    public function isAmountEqual(float $a, float $b): bool
    {
        return abs($a - $b) <= 0.01;
    }

    public function isRedirectMethodConstant($string): bool
    {
        $reflectionClass = new ReflectionClass('Airwallex\Payments\Model\Methods\RedirectMethod');
        $constants = $reflectionClass->getConstants();

        return in_array($string, $constants);
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws Exception
     */
    public function checkIntentWithQuote(
        string $status,
        string $intentCurrency,
        string $quoteCurrency,
        string $intentOrderId,
        string $quoteOrderId,
        float  $intentAmount,
        float  $quoteAmount
    )
    {
        $paidStatus = [
            PaymentIntentInterface::INTENT_STATUS_REQUIRES_CAPTURE,
            PaymentIntentInterface::INTENT_STATUS_SUCCEEDED
        ];
        if (
            !in_array($status, $paidStatus, true)
            || $intentCurrency !== $quoteCurrency
            || $intentOrderId !== $quoteOrderId
            || !$this->isAmountEqual($intentAmount, $quoteAmount)) {
            $this->errorLog->setMessage('check intent failed', "Intent Order ID: $intentOrderId - Quote Order ID: $quoteOrderId - "
                . "Intent Currency: $intentCurrency - Quote Currency: $quoteCurrency - "
                . "Intent Amount: $intentAmount - Quote Amount: $quoteAmount", $intentOrderId)->send();
            $msg = 'Something went wrong while processing your request.';
            throw new Exception(__($msg));
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws Exception
     */
    public function placeOrderByQuoteId(int $quoteId, $from = 'service'): ?int
    {
        $lockKey = 'airwallex_place_order_' . $quoteId;
        if ($this->cache->load($lockKey)) {
            $message = 'Could not obtain lock';
            throw new Exception($message);
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
            } catch (Exception $e) {
            }
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

    public function captureCacheName(string $intentId): string
    {
        return $intentId . '_capture';
    }

    public function refundCacheName(string $intentId): string
    {
        return $intentId . '_refund';
    }
}
