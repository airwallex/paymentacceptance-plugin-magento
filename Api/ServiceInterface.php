<?php
/**
 * Airwallex Payments for Magento
 *
 * MIT License
 *
 * Copyright (c) 2026 Airwallex
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    Airwallex
 * @copyright 2026 Airwallex
 * @license   https://opensource.org/licenses/MIT MIT License
 */
namespace Airwallex\Payments\Api;

use Exception;
use JsonException;
use GuzzleHttp\Exception\GuzzleException;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Account as StructAccount;

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
     * @param string $state Signed return-state token
     * @return string
     */
    public function intent(string $intentId, string $state = ''): string;

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
     * @return StructAccount
     * @throws GuzzleException
     * @throws JsonException
     */
    public function account(): StructAccount;

    /**
     * Conversion quote (MCP)
     *
     * @param string $merchantCurrency
     * @param string $shopperCurrency
     * @return string
     * @throws Exception
     */
    public function conversionQuote(string $merchantCurrency, string $shopperCurrency): string;

    /**
     * Record a client-side checkout funnel stage event (EPP-1214, spec §4 Path A).
     *
     * Relayed here from the storefront and re-emitted to airtracker as onCheckoutFunnel.
     * The stage name is the message "event" field, not the airtracker event name, so a
     * per-attempt funnel (rendered -> selected -> submitted -> succeeded / failed)
     * is computable for source=magento. commonData (appName, source, accountId,
     * appVersion, env, ...) is supplied by the common library, not the client.
     * Counts only — no amounts. Best-effort: always returns a JSON ack and never
     * throws, so telemetry cannot break checkout.
     *
     * @param string $eventName Funnel stage name; must be one of the whitelisted events.
     * @param string $sessionId Random client tracking id used to stitch one checkout attempt.
     * @param string $paymentMethod Payment method code involved in the stage, if any.
     * @param bool $isDefault Whether this reports the default (pre-selected) payment method.
     * @param string $errorReason Optional short failure reason for CHECKOUT_FAILED.
     * @param string $intentId Payment intent id, set when the browser SDK confirm resolves.
     * @return string
     */
    public function trackCheckoutEvent(
        string $eventName,
        string $sessionId,
        string $paymentMethod = '',
        bool $isDefault = false,
        string $errorReason = '',
        string $intentId = ''
    ): string;
}
