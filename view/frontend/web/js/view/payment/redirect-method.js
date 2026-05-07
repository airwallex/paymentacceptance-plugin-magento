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
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'mage/url',
    'mage/storage',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (
    Component,
    $,
    urlBuilder,
    storage,
    utils,
    additionalValidators,
    redirectOnSuccessAction,
    addressHandler,
    quote,
    customerData,
    $t
) {
    'use strict';

    return Component.extend({
        defaults: {
            code: 'redirect',
            iframeSelector: "._active .qrcode-payment .iframe",
            qrcodeSelector: "._active .qrcode-payment .qrcode",
            billingAddress: '',
            afterpayCountryKey: 'awx_afterpay_country',
            bankTransferCurrencyKey: 'awx_bank_transfer_currency',
            timer: null,
            template: 'Airwallex_Payments/payment/redirect-method',
            grandTotal: 0,
            isTotalUpdated: false,
            chosenCurrencyKeyPrefix: 'airwallex_chosen_currency_in_',
            entity: '',
        },

        async loadPayment() {
            const billingTip = $t('Confirm your billing address to use %1')
                .replace('%1', this.getDisplayName(this.index));
            const qrcodeTip = $t('You will be shown the %1 QR code upon confirmation')
                .replace('%1', this.getDisplayName(this.index));
            $(`.${this.index} .awx-billing-address-header`).html(`
                <div class="awx-billing-confirm-tip">${billingTip}</div>
                <div class="awx-qrcode-tip">${qrcodeTip}</div>
            `);

            if (!this.isMethodChecked(this.index)) {
                return;
            }

            this.hideYouPay();

            const currentPaymentMethodCode = quote.paymentMethod().method.replace('airwallex_payments_', '');

            const container = $(`.${this.index} .awx-redirect-method-footer`);

            this.entity = await this.fetchEntity();
            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const entityToCurrency = paymentData.redirect_method_entity_to_currency[currentPaymentMethodCode];
            const countryToCurrency = paymentData.redirect_method_country_to_currency;
            const billingCountry = quote.billingAddress() ? quote.billingAddress().countryId : '';

            if (!entityToCurrency || !entityToCurrency[this.entity]) {
                console.warn('Invalid merchant entity:');
                this.enableCheckoutButton(this.index);
                return;
            }

            const availableCurrencies = paymentData.available_currencies || [];

            if (entityToCurrency[this.entity].indexOf(paymentData.quote_currency_code) !== -1) {
                $(container).html('');
                this.enableCheckoutButton(this.index);
                return;
            }

            if (!availableCurrencies.length) {
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', this.getDisplayName(this.index))
                    .replace('%2', paymentData.quote_currency_code);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.index);
                return;
            }

            if (availableCurrencies.indexOf(paymentData.quote_currency_code) === -1) {
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', this.getDisplayName(this.index))
                    .replace('%2', paymentData.quote_currency_code);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.index);
                return;
            }

            const expressData = await this.fetchExpressData();

            let targetCurrency;
            if (countryToCurrency[billingCountry]) {
                targetCurrency = countryToCurrency[billingCountry];
                localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, targetCurrency);
                if (entityToCurrency[this.entity].indexOf(targetCurrency) === -1) {
                    targetCurrency = '';
                    localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, '');
                }
            }

            if (!targetCurrency && entityToCurrency[this.entity].length === 1) {
                targetCurrency = paymentData.redirect_method_default_currency[currentPaymentMethodCode];
                localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, targetCurrency);
            }

            if (targetCurrency) {
                await this.displaySwitcher('', expressData, targetCurrency, this.getDisplayName(this.index));
                return;
            }

            await this.showCurrencies(expressData);
        },

        async showCurrencies(expressData) {
            let that = this;

            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const currentPaymentMethodCode = quote.paymentMethod().method.replace('airwallex_payments_', '');
            const entityToCurrency = paymentData.redirect_method_entity_to_currency[currentPaymentMethodCode];
            const selectableCurrencies = entityToCurrency[this.entity];

            const currencyItemsHtml = selectableCurrencies
                .map(currency => `<li data-value="${currency}">${currency}</li>`)
                .join('');

            const html = `
                  <div class="awx-selector-container">
                    <div style="margin-bottom: 10px;">Payment currency</div>
                    <div class="input-icon">
                      <img src="${require.toUrl('Airwallex_Payments/assets/select-arrow.svg')}" alt="arrow" />
                    </div>
                    <div>
                      <input readonly style="cursor: pointer" type="text" placeholder="" />
                    </div>
                    <div class="countries" style="display: none">
                      <ul>
                        ${currencyItemsHtml}
                      </ul>
                    </div>
                  </div>
                `;

            let chosenCurrency = localStorage.getItem(this.chosenCurrencyKeyPrefix + this.index);

            if (!chosenCurrency || selectableCurrencies.indexOf(chosenCurrency) === -1) {
                chosenCurrency = paymentData.redirect_method_default_currency[currentPaymentMethodCode];
                localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, chosenCurrency);
            }

            await this.displaySwitcher(html, expressData, chosenCurrency, that.getDisplayName(that.index));

            let chosenCurrenciesList = $(".awx-selector-container li");
            let $chosenCurrencyInput = $(".awx-selector-container input");
            chosenCurrenciesList.each(function () {
                if ($(this).data("value") === chosenCurrency) {
                    $chosenCurrencyInput.val($(this).html());
                    that.enableCheckoutButton(that.index);
                }
            });
            let showCurrencies = function () {
                $(".awx-selector-container .countries").fadeIn(300);
                let country = localStorage.getItem(that.chosenCurrencyKeyPrefix + that.index);
                if (country) {
                    $(".awx-selector-container li").each(function () {
                        $(this).removeClass("selected");
                        if ($(this).data("value") === country) {
                            $(this).addClass("selected");
                        }
                    });
                }
            };
            $chosenCurrencyInput.off('focus').on('focus', showCurrencies);
            $('.awx-selector-container .input-icon').off('click').on('click', showCurrencies);
            $chosenCurrencyInput.off('blur').on('blur', function () {
                $(".awx-selector-container .countries").fadeOut(300);
            });
            chosenCurrenciesList.off('click').on('click', function () {
                let $body = $('body');
                $body.trigger('processStart');
                localStorage.setItem(that.chosenCurrencyKeyPrefix + that.index, $(this).data("value"));
                that.showCurrencies(expressData);
                $body.trigger('processStop');
            });
        },

        initialize() {
            this._super();

            if (!window.awxCardElement && Airwallex) {
                window.awxCardElement = Airwallex.createElement('card');
            }

            quote.billingAddress.subscribe((newValue) => {
                this.renderPayment(newValue, 'billingAddress');
            });

            quote.paymentMethod.subscribe((newValue) => {
                this.hideYouPay();
                this.renderPayment(newValue, 'paymentMethod');
            });

            quote.totals.subscribe((newValue) => {
                this.renderPayment(newValue, 'totals');
            });

            let intentId = utils.getQueryParam('intent_id');
            if (intentId) {
                if (!window['awx_handling_intent_' + intentId]) {
                    window['awx_handling_intent_' + intentId] = true;
                    this.watchPaymentConfirmation(intentId);
                }
            }
        },

        async renderPayment(data, type) {
            $(".awx-redirect-method-footer").show();
            if (type === 'totals') {
                if (Math.abs(this.grandTotal - data.grand_total) < 0.0001) {
                    return;
                }
                if (this.grandTotal === 0 && !this.isTotalUpdated) {
                    this.isTotalUpdated = true;
                    this.grandTotal = data.grand_total;
                    return;
                }
                this.grandTotal = data.grand_total;
            } else if (type === 'billingAddress') {
                if (!data && !this.billingAddress) {
                    return;
                }
                if (data && JSON.stringify(this.billingAddress) === JSON.stringify(data)) {
                    return;
                }
                this.billingAddress = data;
            }

            if (!this.isMethodChecked(this.index)) {
                return;
            }

            if (!quote.billingAddress()) {
                this.disableCheckoutButton(quote.paymentMethod().method);
                return;
            }

            if (this.code !== 'redirect') {
                if (window['awx_rendering_' + this.code]) {
                    return;
                }
                window['awx_rendering_' + this.code] = true;
                setTimeout(() => {
                    window['awx_rendering_' + this.code] = false;
                }, 300);
            }

            await this.callWithCatch(() => this.loadPayment());
        },

        getDisplayName(name) {
            return window.checkoutConfig.payment.airwallex_payments.redirect_method_display_names[name];
        },

        toggleCheckoutButton(method, isEnable) {
            if (!this.isMethodChecked(method)) {
                return;
            }

            $('.' + method + ' .checkout').toggleClass('disabled', !isEnable);
        },

        enableCheckoutButton(method) {
            this.toggleCheckoutButton(method, true);
        },

        disableCheckoutButton(method) {
            this.toggleCheckoutButton(method, false);
        },

        switcherTip(targetCurrency, brand) {
            return $t('%1 We have converted the currency to %2 so you can use %3')
                .replace('%1', '<span class="currency-switcher-tip">')
                .replace('%2', targetCurrency)
                .replace('%3', brand + ".</span>");
        },

        showYouPay(switchers = {}) {
            utils.showYouPay(switchers, $t);
        },

        hideYouPay() {
            utils.hideYouPay();
        },

        hideBillingAddress() {
            const code = quote.paymentMethod().method;
            $('.' + code + ' button.editing').show();
            $('.' + code + ' .payment-method-billing-address').hide();
            $(".awx-redirect-method-footer").hide();
            let refreshSelector = "#" + code + '-button span';
            $(refreshSelector).text($t('Refresh QR code'));
        },

        showBillingAddress() {
            const code = quote.paymentMethod().method;
            $('.' + code + ' button.editing').hide();
            $('.' + code + ' .payment-method-billing-address').show();
            $(".awx-redirect-method-footer").show();
            $(this.iframeSelector).hide();
            $(this.qrcodeSelector).hide();
            let refreshSelector = "#" + code + '-button span';
            $(refreshSelector).text($t('Confirm'));
        },

        isMethodChecked(method) {
            if (!quote.paymentMethod() || !quote.paymentMethod().method) {
                return false;
            }
            return quote.paymentMethod().method === method;
        },

        async callWithCatch(fn) {
            let $body = $('body');
            $body.trigger('processStart');
            try {
                await fn();
            } catch (e) {
                const container = $(`.` + quote.paymentMethod().method + ` .awx-redirect-method-footer`);
                let msg = $t('Something went wrong while processing your request. Please try again.');
                if (e && e.responseJSON && e.responseJSON.message) {
                    msg = e.responseJSON.message;
                }
                console.error('Error during payment processing:', e);
                let $errorElem = $('.' + quote.paymentMethod().method + ' .awx-alert');
                if ($errorElem.length) {
                    $errorElem.remove();
                }
                container.append(utils.awxAlert(msg));
                this.enableCheckoutButton(this.index);
            }
            $body.trigger('processStop');
        },

        async placeOrder(data, event) {
            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                if (this.code === 'redirect') {
                    $('.' + quote.paymentMethod().method + ' .awx-alert').remove();
                }
                await this.callWithCatch(() => this._placeOrder());
                return true;
            }

            return false;
        },

        async _placeOrder() {
            let device_id = '';
            if (document.getElementById('airwallex-fraud-api')) {
                device_id = document.getElementById('airwallex-fraud-api').getAttribute('data-order-session-id');
            }
            const payload = {
                cartId: quote.getQuoteId(),
                paymentMethod: {
                    method: quote.paymentMethod().method,
                    additional_data: {
                        "afterpay_country": localStorage.getItem(this.afterpayCountryKey),
                        "bank_transfer_currency": localStorage.getItem(this.bankTransferCurrencyKey),
                        "redirect_method_chosen_currency": localStorage.getItem(this.chosenCurrencyKeyPrefix + this.index),
                        "browser_information": JSON.stringify({
                            "device_data": {
                                "browser": {
                                    "java_enabled": false,
                                    "javascript_enabled": true,
                                    "user_agent": navigator.userAgent
                                },
                                "device_id": device_id,
                                "language": navigator.language,
                                "screen_color_depth": screen.colorDepth,
                                "screen_height": screen.height,
                                "screen_width": screen.width,
                                "timezone": new Date().getTimezoneOffset()
                            }
                        })
                    },
                    extension_attributes: {
                        'agreement_ids': utils.getAgreementIds()
                    }
                },
            };

            await utils.setRecaptchaToken(payload, utils.getRecaptchaId());

            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }

            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());

            let intentResponse = await utils.getIntent(payload, {});
            this.renderQrcode(intentResponse);
            this.watchPaymentConfirmation(intentResponse.intent_id);
        },

        renderQrcode(intentResponse) {
            let $body = $('body');
            let nextAction = JSON.parse(intentResponse.next_action);
            if (nextAction.type === 'redirect' && quote.paymentMethod().method !== 'airwallex_payments_pay_now') {
                utils.clearDataAfterPay({}, customerData);
                $body.trigger('processStart');
                location.href = nextAction.url;
                return;
            }
            $(this.iframeSelector).html('').hide();
            $(this.qrcodeSelector).html('').hide();
            $("._active .qrcode-payment").css('display', 'flex');
            this.hideBillingAddress();
            if (['airwallex_payments_pay_now'].indexOf(quote.paymentMethod().method) === -1) {
                $(this.qrcodeSelector).show();
                new QRCode(document.querySelector(this.qrcodeSelector), nextAction.qrcode);
                $body.trigger('processStop');
            } else {
                $(this.iframeSelector).show();
                $(this.iframeSelector).html(`<iframe src="${nextAction.url}"></iframe>`);
                const iframeElement = $(this.iframeSelector).find('iframe');
                let iframeSelector = this.iframeSelector;
                let setHeight = function () {
                    let height = 1200;
                    if (window.innerWidth > 768 && window.innerWidth <= 1000) {
                        height = window.innerWidth * 1.3;
                    } else if (window.innerWidth > 550 && window.innerWidth < 640) {
                        height = window.innerWidth * 2;
                    } else if (window.innerWidth <= 550) {
                        height = 1100;
                    }
                    $(iframeElement).height(height);
                };
                setHeight();
                iframeElement.on('load', function () {
                    let iframeTop = $(iframeSelector).offset().top;
                    let iframeHeight = $(iframeSelector).outerHeight();
                    let windowHeight = $(window).height();
                    let scrollToPosition = iframeTop - (windowHeight / 2) + (iframeHeight / 2);
                    $('html, body').animate({
                        scrollTop: scrollToPosition
                    }, 'slow');

                    window.addEventListener('resize', setHeight);
                    $body.trigger('processStop');
                });
            }
        },

        watchPaymentConfirmation(intent_id) {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }

            this.timer = setInterval(async () => {
                try {
                    const intentResult = await this.getIntent(intent_id);
                    let intentResponse = JSON.parse(intentResult);
                    if (intentResponse.paid && intentResponse.is_order_handled_success) {
                        clearInterval(this.timer);
                        this.timer = null;
                        utils.clearDataAfterPay(intentResponse, customerData);
                        redirectOnSuccessAction.execute();
                    }
                } catch (err) {
                    console.error('Error while polling payment intent:', err);
                }
            }, 5000);
        },

        async displaySwitcher(html, expressData, targetCurrency, brand) {
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            const switchers = await this.switcher(expressData.quote_currency_code, targetCurrency, expressData.grand_total);
            container.html(html + this.switcherTip(targetCurrency, brand));
            this.showYouPay(switchers);
            this.enableCheckoutButton(this.index);
        },

        async getIntent(intentId) {
            const requestUrl = urlBuilder.build('rest/V1/airwallex/payments/intent?intent_id=' + intentId);
            return await storage.get(requestUrl, undefined, 'application/json', {});
        },

        async switcher(payment_currency, target_currency, amount) {
            const requestUrl = urlBuilder.build('rest/V1/airwallex/currency/switcher');
            const res = await storage.post(requestUrl, JSON.stringify({
                'payment_currency': payment_currency,
                'target_currency': target_currency,
                'amount': amount,
            }), undefined, 'application/json', {});
            return JSON.parse(res);
        },

        async fetchExpressData() {
            const url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
            const resp = await storage.get(url, undefined, 'application/json', {});
            return JSON.parse(resp);
        },

        async fetchEntity() {
            const accountUrl = urlBuilder.build('rest/V1/airwallex/account');
            const accountResp = await storage.get(accountUrl, undefined, 'application/json', {});
            return accountResp.owning_entity;
        },
    });
});
