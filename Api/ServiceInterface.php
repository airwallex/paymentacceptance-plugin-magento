<?php

namespace Airwallex\Payments\Api;

use Exception;
use JsonException;
use GuzzleHttp\Exception\GuzzleException;

interface ServiceInterface
{
    /**
     * Get express data when initialize and quote data updated
     *
     * @return string
     */
    public function expressData(): string;

    /**
     * Get intent
     *
     * @param string $intentId
     * @return string
     */
    public function intent(string $intentId): string;

    /**
     * Add to cart
     *
     * @return string
     */
    public function addToCart(): string;

    /**
     * Post Address to get method and quote data
     *
     * @return string
     */
    public function postAddress(): string;

    /**
     * Get region id
     *
     * @param string $country
     * @param string $region
     * @return string
     */
    public function regionId(string $country, string $region): string;

    /**
     * Apple pay validate merchant
     *
     * @return string
     * @throws Exception
     */
    public function validateMerchant(): string;

    /**
     * Validate addresses before placing order
     *
     * @return string
     * @throws Exception
     */
    public function validateAddresses(): string;

    /**
     * Account detail
     *
     * @return string
     * @throws GuzzleException
     * @throws JsonException
     */
    public function account(): string;

    /**
     * Currency switcher
     *
     * @param string $paymentCurrency
     * @param string $targetCurrency
     * @param string $amount
     * @return string
     * @throws Exception
     */
    public function currencySwitcher(string $paymentCurrency, string $targetCurrency, string $amount): string;
}
