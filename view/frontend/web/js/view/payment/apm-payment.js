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
], function ($, $t, customerData, utils) {
    'use strict';

    return function (config) {
        const paymentConfig = config.config;
        if (!paymentConfig) {
            showError($t('Payment configuration is missing.'));
            return;
        }

        let apmElement = null;

        function showError(message) {
            $('#airwallex-error-message').text(message).show();
        }

        function showLoading() {
            $('#airwallex-loading-overlay').css('display', 'flex');
        }

        function hideLoading() {
            $('#airwallex-loading-overlay').css('display', 'none');
        }

        function initializeAirwallex() {
            try {
                const env = paymentConfig.env === 'demo' ? 'demo' : 'prod';

                Airwallex.init({
                    env: env,
                    origin: window.location.origin,
                });

                createDropInElement();
            } catch (error) {
                hideLoading();
                showError($t('Failed to initialize payment system. Please try again.'));
            }
        }

        function createDropInElement() {
            apmElement = Airwallex.createElement('dropIn', paymentConfig.elementOptions);
            apmElement.mount('airwallex-apm-element');
            bindElementEvents();
        }

        function displayCurrencyConversion(quote) {
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
            apmElement.on('ready', function () {
                hideLoading();
            });

            apmElement.on('success', function () {
                showLoading();
                window.location.href = paymentConfig.return_url;
            });

            apmElement.on('error', function (event) {
                hideLoading();
                removeCurrencyConversion();

                const errorDetail = event?.detail?.error;
                if (errorDetail && errorDetail.code === 'no_payment_methods') {
                    showError(errorDetail.message || $t('No payment methods available. Please contact support.'));
                }
            });

            apmElement.on('cancel', function () {
                hideLoading();
                removeCurrencyConversion();
                showError($t('Payment was cancelled.'));
            });

            apmElement.on('quoteCreate', function (e) {
                displayCurrencyConversion(e?.detail?.quote);
            });
        }

        $(document).ready(function () {
            initializeAirwallex();
        });
    };
});
