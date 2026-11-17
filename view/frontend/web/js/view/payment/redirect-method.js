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
/** `this` receiver for the redirect renderer's methods. */
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'ko',
    'mage/url',
    'mage/storage',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/tracking',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (
    Component                        ,
    $              ,
    ko                ,
    urlBuilder            ,
    storage             ,
    utils                     ,
    tracking   
     ,
    additionalValidators                      ,
    redirectOnSuccessAction                         ,
    addressHandler                      ,
    quote            ,
    customerData         ,
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
            renderVersion: 0,
            renderQueued: false,
            isSubmitting: false,
            isDisposed: false,
        },
        beginRender(                      ) {
            ++this.renderVersion;
            this.disableCheckoutButton(this.index);
            return this.renderVersion;
        },
        isRenderCurrent(                        version        ) {
            return version === this.renderVersion && this.isMethodChecked(this.index) && !!quote.billingAddress();
        },
        async loadPayment(                      ) {
            const billingTip = $t('Confirm your billing address to use %1')
                .replace('%1', this.getDisplayName(this.index));
            const qrcodeTip = $t('You will be shown the %1 QR code upon confirmation')
                .replace('%1', this.getDisplayName(this.index));
            $(`.${this.index} .awx-billing-address-header`).empty().append(
                $('<div class="awx-billing-confirm-tip"></div>').text(billingTip),
                $('<div class="awx-qrcode-tip"></div>').text(qrcodeTip)
            );
            if (!this.isMethodChecked(this.index)) {
                return;
            }
            const version = this.beginRender();
            this.hideYouPay();
            localStorage.removeItem(this.chosenCurrencyKeyPrefix + this.index);
            const currentPaymentMethodCode = quote.paymentMethod().method.replace('airwallex_payments_', '');
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            this.entity = await this.fetchEntity();
            if (!this.isRenderCurrent(version)) return;
            const paymentData = window.checkoutConfig .payment .airwallex_payments ;
            const entityToCurrency = paymentData.redirect_method_entity_to_currency [currentPaymentMethodCode];
            const countryToCurrency = paymentData.redirect_method_country_to_currency ;
            const billingCountry = quote.billingAddress() ? quote.billingAddress().countryId : '';
            if (!entityToCurrency || !entityToCurrency[this.entity]) {
                this.showUnavailableCurrencyAlert(container, this.index, this.getDisplayName(this.index), paymentData.quote_currency_code );
                return;
            }
            const availableCurrencies = paymentData.available_currencies || [];
            if (entityToCurrency[this.entity].indexOf(paymentData.quote_currency_code ) !== -1) {
                $(container).html('');
                this.enableCheckoutButton(this.index);
                return;
            }
            if (!availableCurrencies.length || availableCurrencies.indexOf(paymentData.quote_currency_code ) === -1) {
                this.showUnavailableCurrencyAlert(
                    container,
                    this.index,
                    this.getDisplayName(this.index),
                    paymentData.quote_currency_code 
                );
                return;
            }
            const expressData = await this.fetchExpressData();
            if (!this.isRenderCurrent(version)) return;
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
                targetCurrency = paymentData.redirect_method_default_currency [currentPaymentMethodCode];
                localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, targetCurrency);
            }
            if (targetCurrency) {
                await this.displaySwitcher('', expressData, targetCurrency, this.getDisplayName(this.index));
                return;
            }
            await this.showCurrencies(expressData);
        },
        async showCurrencies(                        expressData     ) {
            const version = this.renderVersion;
            let that = this;
            const paymentData = window.checkoutConfig .payment .airwallex_payments ;
            const currentPaymentMethodCode = quote.paymentMethod().method.replace('airwallex_payments_', '');
            const entityToCurrency = paymentData.redirect_method_entity_to_currency [currentPaymentMethodCode];
            const selectableCurrencies = entityToCurrency[this.entity];
            const currencyItemsHtml = selectableCurrencies
                .map(currency => `<li data-value="${this.escapeHtml(currency)}">${this.escapeHtml(currency)}</li>`)
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
                chosenCurrency = paymentData.redirect_method_default_currency [currentPaymentMethodCode];
                localStorage.setItem(this.chosenCurrencyKeyPrefix + this.index, chosenCurrency);
            }
            await this.displaySwitcher(html, expressData, chosenCurrency, that.getDisplayName(that.index));
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            if (!this.isRenderCurrent(version)) return;
            let chosenCurrenciesList = container.find('.awx-selector-container li');
            let $chosenCurrencyInput = container.find('.awx-selector-container input');
            chosenCurrenciesList.each(function (                 ) {
                if ($(this).data("value") === chosenCurrency) {
                    $chosenCurrencyInput.val($(this).text());
                    that.enableCheckoutButton(that.index);
                }
            });
            let showCurrencies = function () {
                container.find('.awx-selector-container .countries').fadeIn(300);
                let country = localStorage.getItem(that.chosenCurrencyKeyPrefix + that.index);
                if (country) {
                    container.find('.awx-selector-container li').each(function (                 ) {
                        $(this).removeClass("selected");
                        if ($(this).data("value") === country) {
                            $(this).addClass("selected");
                        }
                    });
                }
            };
            $chosenCurrencyInput.off('focus').on('focus', showCurrencies);
            container.find('.awx-selector-container .input-icon').off('click').on('click', showCurrencies);
            $chosenCurrencyInput.off('blur').on('blur', function () {
                container.find('.awx-selector-container .countries').fadeOut(300);
            });
            chosenCurrenciesList.off('click').on('click', function (                 ) {
                void that.selectCurrency(that.chosenCurrencyKeyPrefix + that.index, $(this).data("value"), () => that.showCurrencies(expressData));
            });
        },
        initialize(                      ) {
            this._super();
            this.checkoutSubscriptions = [];
            this.billingAddress = ko.toJS(quote.billingAddress());
            if (!window.awxCardElement && Airwallex) {
                window.awxCardElement = Airwallex.createElement('card');
            }
            this.checkoutSubscriptions.push(quote.billingAddress.subscribe((newValue) => {
                this.renderPayment(newValue, 'billingAddress');
            }));
            this.checkoutSubscriptions.push(quote.paymentMethod.subscribe((newValue) => {
                ++this.renderVersion;
                if (!newValue || newValue.method !== this.index) return;
                this.hideYouPay();
                this.renderPayment(newValue, 'paymentMethod');
            }));
            this.checkoutSubscriptions.push(quote.totals.subscribe((newValue) => {
                this.renderPayment(newValue, 'totals');
            }));
            let intentId = utils.getQueryParam('intent_id');
            if (intentId) {
                if (!window['awx_handling_intent_' + intentId]) {
                    window['awx_handling_intent_' + intentId] = true;
                    this.watchPaymentConfirmation(intentId, utils.getQueryParam('state'));
                }
            }
            this.trackActiveMethod();
        },
        destroy(                      ) {
            this.isDisposed = true;
            ++this.renderVersion;
            this.checkoutSubscriptions?.forEach((subscription                      ) => subscription.dispose());
            return this._super();
        },
        funnelMethod(                      ) {
            return String(this.index).replace('airwallex_payments_', '').replace('airwallex_cc_', '');
        },
        trackActiveMethod(                      ) {
            if (!this.isMethodChecked(this.index)) {
                return;
            }
            const paymentMethod = this.funnelMethod();
            tracking.trackRendered(paymentMethod);
            tracking.trackMethodSelected(paymentMethod);
        },
        async renderPayment(                        data     , type        ) {
            if (type === 'totals') {
                if (!data) return;
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
                this.billingAddress = ko.toJS(data);
                this.selectedAfterpayCountry = null;
            }
            if (!this.isMethodChecked(this.index)) {
                return;
            }
            ++this.renderVersion;
            if (!quote.billingAddress()) {
                this.disableCheckoutButton(this.index);
                return;
            }
            this.disableCheckoutButton(this.index);
            if (this.renderQueued) return;
            this.renderQueued = true;
            ko.tasks.schedule(() => {
                this.renderQueued = false;
                if (!this.isMethodChecked(this.index) || !quote.billingAddress()) return;
                $(`.${this.index} .awx-redirect-method-footer`).show();
                this.trackActiveMethod();
                void this.callWithCatch(() => this.loadPayment(), false);
            });
        },
        onPaymentContainerRendered(                      ) {
            void this.renderPayment(null, 'render');
        },
        getDisplayName(name        ) {
            return window.checkoutConfig .payment .airwallex_payments .redirect_method_display_names [name];
        },
        getMethodLogo(                      ) {
            const config = window.checkoutConfig .payment .airwallex_payments ;
            const logos = config.redirect_method_logos || {};
            const name = (this.index || this.code || '').replace('airwallex_payments_', '');
            return logos[name] || '';
        },
        hasMethodLogo(                      ) {
            return !!this.getMethodLogo();
        },
        toggleCheckoutButton(                        method        , isEnable         ) {
            if (!this.isMethodChecked(method)) {
                return;
            }
            $('.' + method + ' .checkout').toggleClass('disabled', !isEnable).prop('disabled', !isEnable);
        },
        enableCheckoutButton(                        method        ) {
            this.toggleCheckoutButton(method, true);
        },
        disableCheckoutButton(                        method        ) {
            this.toggleCheckoutButton(method, false);
        },
        escapeHtml(value         )         {
            return $('<div/>').text(String(value ?? '')).html();
        },
        showUnavailableCurrencyAlert(
            container        ,
            method        ,
            brand        ,
            currency        
        ) {
            const msg = this.escapeHtml($t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.'))
                .replace('%1', this.escapeHtml(brand))
                .replace('%2', this.escapeHtml(currency));
            container.html(utils.awxAlert(msg));
            this.disableCheckoutButton(method);
        },
        switcherTip(                        targetCurrency        , brand        ) {
            return this.escapeHtml($t('%1 We have converted the currency to %2 so you can use %3'))
                .replace('%1', '<span class="currency-switcher-tip">')
                .replace('%2', this.escapeHtml(targetCurrency))
                .replace('%3', this.escapeHtml(brand) + ".</span>");
        },
        showYouPay(                        switchers          = {}) {
            if (this.isMethodChecked(this.index)) utils.showYouPay(switchers, $t);
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
        showBillingAddress(                      ) {
            const code = quote.paymentMethod().method;
            $('.' + code + ' button.editing').hide();
            $('.' + code + ' .payment-method-billing-address').show();
            $(".awx-redirect-method-footer").show();
            $(this.iframeSelector).hide();
            $(this.qrcodeSelector).hide();
            let refreshSelector = "#" + code + '-button span';
            $(refreshSelector).text($t('Confirm'));
        },
        isMethodChecked(                        method        ) {
            if (this.isDisposed || !quote.paymentMethod() || !quote.paymentMethod().method) {
                return false;
            }
            return quote.paymentMethod().method === method;
        },
        async selectCurrency(                        key        , selected        , render                                       ) {
            const pending = this.currencySelection;
            const previous = pending?.key === key && pending.version === this.renderVersion ? pending.previous : localStorage.getItem(key);
            const version = this.beginRender();
            this.currencySelection = { key, previous, version };
            await this.callWithCatch(async () => {
                localStorage.setItem(key, selected);
                try {
                    await render(version);
                } catch (e) {
                    if (!this.isRenderCurrent(version)) return;
                    if (previous === null) localStorage.removeItem(key);
                    else localStorage.setItem(key, previous);
                    throw e;
                } finally {
                    if (this.currencySelection?.version === version) this.currencySelection = null;
                }
            }, !!previous);
        },
        async callWithCatch(                        fn               , enableOnError = true) {
            let version = this.renderVersion;
            const $body = $('body');
            $body.trigger('processStart');
            try {
                const result = fn();
                version = this.renderVersion;
                await result;
            } catch (e     ) {
                if (!this.isRenderCurrent(version)) return;
                const container = $(`.${this.index} .awx-redirect-method-footer`);
                const msg = e?.responseJSON?.message || e?.message || $t('Something went wrong while processing your request. Please try again.');
                console.error('Error during payment processing:', e);
                const paymentMethod = this.funnelMethod();
                tracking.useSession(paymentMethod);
                tracking.track(tracking.EVENTS.FAILED, { payment_method: paymentMethod, error_reason: msg });
                container.find('.awx-alert').remove();
                container.append(utils.awxAlert(this.escapeHtml(msg)));
                this.toggleCheckoutButton(this.index, enableOnError);
            } finally {
                $body.trigger('processStop');
            }
        },
        async placeOrder(                        data          , event        ) {
            if (event) event.preventDefault();
            if (this.isSubmitting || !this.isMethodChecked(this.index) || $(`.${this.index} .checkout`).hasClass('disabled')) return false;
            if (!this.validate() || !additionalValidators.validate()) return false;
            this.isSubmitting = true;
            const version = this.renderVersion;
            this.disableCheckoutButton(this.index);
            try {
                await this.callWithCatch(() => this._placeOrder());
                return true;
            } finally {
                this.isSubmitting = false;
                if (this.isRenderCurrent(version)) this.enableCheckoutButton(this.index);
            }
        },
        async _placeOrder(                      ) {
            const paymentMethod = this.funnelMethod();
            tracking.useSession(paymentMethod);
            tracking.track(tracking.EVENTS.SUBMITTED, { payment_method: paymentMethod });
            let device_id                = '';
            if (document.getElementById('airwallex-fraud-api')) {
                device_id = document.getElementById('airwallex-fraud-api') .getAttribute('data-order-session-id');
            }
            const payload                            = {
                cartId: quote.getQuoteId(),
                paymentMethod: {
                    method: this.index,
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
            if (!this.isMethodChecked(this.index)) return;
            if (!utils.isLoggedIn()) {
                payload.email = quote.guestEmail;
            }
            await addressHandler.postBillingAddress({
                'cartId': quote.getQuoteId(),
                'address': quote.billingAddress()
            }, utils.isLoggedIn(), quote.getQuoteId());
            if (!this.isMethodChecked(this.index)) return;
            let intentResponse = await utils.getIntent(payload, {});
            if (utils.completeRecoveredOrder(intentResponse)) return;
            if (!this.isMethodChecked(this.index)) return;
            this.renderQrcode(intentResponse);
            this.watchPaymentConfirmation(intentResponse.intent_id, intentResponse.state);
        },
        renderQrcode(                        intentResponse     ) {
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
                $(this.iframeSelector).empty().append($('<iframe></iframe>').attr('src', nextAction.url));
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
                    let iframeTop = $(iframeSelector).offset() .top;
                    let iframeHeight = $(iframeSelector).outerHeight() ;
                    let windowHeight = $(window).height() ;
                    let scrollToPosition = iframeTop - (windowHeight / 2) + (iframeHeight / 2);
                    $('html, body').animate({
                        scrollTop: scrollToPosition
                    }, 'slow');
                    window.addEventListener('resize', setHeight);
                    $body.trigger('processStop');
                });
            }
        },
        watchPaymentConfirmation(                        intent_id        , state                ) {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            this.timer = utils.watchPaymentConfirmation(intent_id, (intentResponse) => {
                this.timer = null;
                utils.clearDataAfterPay(intentResponse, customerData);
                redirectOnSuccessAction.execute();
            }, (error) => {
                console.error('Error while polling payment intent:', error);
            }, state || undefined);
        },
        async displaySwitcher(                        html        , expressData     , targetCurrency        , brand        ) {
            const version = this.renderVersion;
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            const switchers = await this.switcher(expressData.quote_currency_code, targetCurrency, expressData.grand_total);
            if (!this.isRenderCurrent(version)) return;
            container.html(html + this.switcherTip(targetCurrency, brand));
            this.showYouPay(switchers);
            this.enableCheckoutButton(this.index);
        },
        async getIntent(intentId        , state                ) {
            let requestUrl = urlBuilder.build('rest/V1/airwallex/payments/intent?intent_id=' + intentId);
            if (state) {
                requestUrl += '&state=' + encodeURIComponent(state);
            }
            return await storage.get(requestUrl, undefined, 'application/json', {});
        },
        async switcher(payment_currency        , target_currency        , amount        ) {
            const quoteResp = await utils.conversionQuote(payment_currency, target_currency);
            const rate = Number(quoteResp.conversion_rate);
            return {
                id: quoteResp.id,
                payment_currency: payment_currency,
                target_currency: target_currency,
                payment_amount: amount,
                target_amount: Number(amount) * rate,
                client_rate: quoteResp.conversion_rate,
            };
        },
        async fetchExpressData() {
            const url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
            const resp = await storage.get(url, undefined, 'application/json', {});
            return typeof resp === 'string' ? JSON.parse(resp) : resp;
        },
        async fetchEntity() {
            const accountUrl = urlBuilder.build('rest/V1/airwallex/account');
            const accountResp = await storage.get(accountUrl, undefined, 'application/json', {});
            return accountResp.owning_entity;
        },
    });
});
