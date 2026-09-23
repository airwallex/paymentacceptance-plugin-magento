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
define([
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Airwallex_Payments/js/view/payment/utils'
], function (
    $              ,
    $t                          ,
    customerData         ,
    utils             
) {
    'use strict';
    return function (config                      ) {
        const paymentConfig = config.config;
        if (!paymentConfig) {
            showError($t('Payment configuration is missing.'));
            return;
        }
        let apmElement                          = null;
        let currentCurrency = (paymentConfig.order_currency || paymentConfig.intent_base_currency || '').toUpperCase();
        let currencyToken = 0;
        let billingCountryCode = '';
        // The standalone APM page has no `window.checkoutConfig`; mirror the
        // per-page maps onto it so shared consumers (utils.renderCurrencySwitcher)
        // can resolve currency<->country codes for flag lookups.
        const checkoutConfig = window.checkoutConfig = window.checkoutConfig || {};
        checkoutConfig.payment = checkoutConfig.payment || {};
        const awxConfig = checkoutConfig.payment.airwallex_payments = checkoutConfig.payment.airwallex_payments || {};
        if (paymentConfig.country_to_currency && !awxConfig.country_to_currency) {
            awxConfig.country_to_currency = paymentConfig.country_to_currency;
        }
        if (paymentConfig.currency_to_country && !awxConfig.currency_to_country) {
            awxConfig.currency_to_country = paymentConfig.currency_to_country;
        }
        if (paymentConfig.eu_country_codes && !awxConfig.eu_country_codes) {
            awxConfig.eu_country_codes = paymentConfig.eu_country_codes;
        }
        function showError(message        ) {
            $('#airwallex-error-message').text(message).show();
        }
        function showLoading() {
            $('#airwallex-loading-overlay').css('display', 'flex');
        }
        function hideLoading() {
            $('#airwallex-loading-overlay').css('display', 'none');
        }
        async function initializeAirwallex() {
            try {
                // components-sdk only accepts 'demo' for sandbox; map accordingly.
                const env = (paymentConfig .env === 'sandbox' || paymentConfig .env === 'demo') ? 'demo' : 'prod';
                Airwallex.init({
                    env: env,
                    origin: window.location.origin,
                });
                const elementOptions = (paymentConfig .elementOptions || {})                                                                                                  ;
                paymentConfig .elementOptions = elementOptions;
                billingCountryCode = (elementOptions.country_code || '').toUpperCase();
                const countryToCurrency = awxConfig.country_to_currency || {};
                const billingCurrency = (countryToCurrency[billingCountryCode] || '').toUpperCase();
                const orderCurrency = (paymentConfig .order_currency || '').toUpperCase();
                if (billingCurrency && orderCurrency && billingCurrency !== orderCurrency) {
                    try {
                        const quoteResp = await utils.conversionQuote(orderCurrency, billingCurrency);
                        elementOptions.currency = billingCurrency;
                        elementOptions.quote_id = quoteResp.id;
                        const rate = Number(quoteResp.conversion_rate);
                        const targetAmount = Number(paymentConfig .intent_base_amount || 0) * rate;
                        displayCurrencyConversion({
                            target_amount: targetAmount,
                            target_currency: billingCurrency,
                            payment_currency: orderCurrency,
                            client_rate: quoteResp.conversion_rate
                        });
                        currentCurrency = billingCurrency;
                        renderCurrencySwitcher();
                    } catch {
                        // Pre-quote failed — drop back to order currency, don't show switcher
                        hideCurrencySwitcher();
                        removeCurrencyConversion();
                    }
                } else {
                    hideCurrencySwitcher();
                    removeCurrencyConversion();
                }
                createDropInElement();
            } catch (error) {
                hideLoading();
                showError($t('Failed to initialize payment system. Please try again.'));
            }
        }
        function createDropInElement() {
            const elementOptions = (paymentConfig .elementOptions || {})                                                                              ;
            elementOptions.disableAutoCurrencyConversion = true;
            paymentConfig .elementOptions = elementOptions;
            apmElement = Airwallex.createElement('dropIn', elementOptions);
            apmElement.mount('airwallex-apm-element');
            bindElementEvents();
        }
        function renderCurrencySwitcher() {
            const orderCurrency = (paymentConfig .order_currency || '').toUpperCase();
            const currencies = utils.buildSwitcherCurrencies(orderCurrency, paymentConfig .available_currencies);
            if (currencies.length === 0) {
                return;
            }
            utils.renderCurrencySwitcher(
                '#airwallex-currency-switcher',
                currencies,
                currentCurrency,
                billingCountryCode,
                onCurrencyChange,
                $t
            );
        }
        function hideCurrencySwitcher() {
            $('#airwallex-currency-switcher').empty().hide();
        }
        async function onCurrencyChange(selected        ) {
            if (!apmElement || selected === currentCurrency) {
                return;
            }
            const orderCurrency = (paymentConfig .order_currency || '').toUpperCase();
            if (selected === orderCurrency) {
                currentCurrency = selected;
                apmElement.update({
                    currency: selected,
                    quote_id: undefined
                });
                removeCurrencyConversion();
                return;
            }
            const token = ++currencyToken;
            let quoteResp                         ;
            try {
                quoteResp = await utils.conversionQuote(orderCurrency, selected);
            } catch (e) {
                let msg = $t('Failed to convert currency. Please try again.');
                const err = e                                                             ;
                if (err && err.responseJSON && err.responseJSON.message) {
                    msg = err.responseJSON.message;
                } else if (err && err.message) {
                    msg = err.message;
                }
                showError(msg);
                return;
            }
            if (token !== currencyToken) {
                return;
            }
            currentCurrency = selected;
            apmElement.update({
                quote_id: quoteResp.id,
                currency: selected
            });
            const rate = Number(quoteResp.conversion_rate);
            const targetAmount = Number(paymentConfig .intent_base_amount || 0) * rate;
            displayCurrencyConversion({
                target_amount: targetAmount,
                target_currency: selected,
                payment_currency: orderCurrency,
                client_rate: quoteResp.conversion_rate
            });
        }
        function displayCurrencyConversion(quote                           ) {
            if (!quote) {
                removeCurrencyConversion();
                return;
            }
            let formattedTargetAmount = utils.convertToAwxAmount(quote.target_amount, quote.target_currency);
            let formattedClientRate = quote.client_rate;
            let rateText = '1 ' + quote.payment_currency + ' = ' +
                           formattedClientRate + ' ' + quote.target_currency;
            let amountText = quote.target_currency + ' ' + formattedTargetAmount;
            $('#airwallex-conversion-rate').text(rateText);
            $('#airwallex-conversion-amount').text(amountText);
            $('#airwallex-currency-conversion').show();
        }
        function removeCurrencyConversion() {
            $('#airwallex-currency-conversion').hide();
        }
        function bindElementEvents() {
            apmElement .on('ready', function () {
                hideLoading();
            });
            apmElement .on('success', function () {
                showLoading();
                window.location.href = paymentConfig .return_url ;
            });
            apmElement .on('error', function (event                                                                        ) {
                hideLoading();
                removeCurrencyConversion();
                const errorDetail = event?.detail?.error;
                if (errorDetail && errorDetail.code === 'no_payment_methods') {
                    showError(errorDetail.message || $t('No payment methods available. Please contact support.'));
                }
            });
            apmElement .on('cancel', function () {
                hideLoading();
            });
            apmElement .on('quoteCreate', function (e                                                             ) {
                const quote = e?.detail?.quote;
                const eventCurrency = (quote?.target_currency || '').toUpperCase();
                const orderCurrency = (paymentConfig .order_currency || '').toUpperCase();
                if (eventCurrency && orderCurrency && eventCurrency === orderCurrency) {
                    removeCurrencyConversion();
                    return;
                }
                displayCurrencyConversion(quote);
                if (eventCurrency) {
                    currentCurrency = eventCurrency;
                    utils.setSelectedCurrency('#airwallex-currency-switcher', eventCurrency);
                }
            });
        }
        $(document).ready(function () {
            initializeAirwallex();
        });
    };
});
