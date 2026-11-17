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
/** `this` receiver shared by the redirect-method child renderers. */
define([
    "Airwallex_Payments/js/view/payment/redirect-method",
    "ko",
    "jquery",
    'Magento_Checkout/js/model/quote',
    'Airwallex_Payments/js/view/payment/utils',
    'mage/translate'
], function (
    Component                        ,
    ko                ,
    $              ,
    quote                         ,
    utils                          ,
    $t             
) {
    "use strict";
    return Component.extend({
        defaults: {
            code: 'airwallex_payments_afterpay',
            template: "Airwallex_Payments/payment/redirect-method",
        },
        selectedAfterpayCountry: null,
        getSupportedAfterpayCountries(                      ) {
            const config = window.checkoutConfig .payment .airwallex_payments ;
            const supported = config.afterpay_support_entity_to_currency [this.entity] || [];
            const available = config.available_currencies || [];
            const base = config.quote_currency_code ;
            return Object.keys(config.afterpay_support_countries ).filter(country => {
                const currency = config.afterpay_support_countries [country];
                return supported.indexOf(currency) !== -1 && (currency === base ||
                    (available.indexOf(base) !== -1 && available.indexOf(currency) !== -1));
            });
        },
        async loadPayment(                      ) {
            if (!this.isMethodChecked(this.code)) return;
            const version = this.beginRender();
            this.hideYouPay();
            this.entity = await this.fetchEntity();
            if (!this.isRenderCurrent(version)) return;
            const config = window.checkoutConfig .payment .airwallex_payments ;
            const countryToCurrency = config.afterpay_support_countries ;
            const countries = this.getSupportedAfterpayCountries();
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            if (!countries.length) {
                localStorage.removeItem(this.afterpayCountryKey);
                this.selectedAfterpayCountry = null;
                this.showUnavailableCurrencyAlert(container, this.code, 'Afterpay', config.quote_currency_code );
                return;
            }
            if (countries.indexOf(this.selectedAfterpayCountry) === -1) this.selectedAfterpayCountry = null;
            const billingCountry = quote.billingAddress()?.countryId || '';
            const defaultCountry = countries.indexOf(billingCountry) !== -1 ? billingCountry :
                (countries.length === 1 ? countries[0] : '');
            const country = this.selectedAfterpayCountry || defaultCountry;
            localStorage.setItem(this.afterpayCountryKey, country);
            const needsCountrySelector = !!this.selectedAfterpayCountry || !country;
            if (!needsCountrySelector && countryToCurrency[country] === config.quote_currency_code) {
                container.empty();
                this.enableCheckoutButton(this.code);
                return;
            }
            const expressData = await this.fetchExpressData();
            if (!this.isRenderCurrent(version)) return;
            if (needsCountrySelector) await this.showAfterpayCountries(expressData);
            else await this.displaySwitcher('', expressData, countryToCurrency[country], 'Afterpay');
        },
        async showAfterpayCountries(                        expressData     ) {
            const version = this.renderVersion;
            if (!this.entity) this.entity = await this.fetchEntity();
            if (!this.isRenderCurrent(version)) return;
            const countries           = this.getSupportedAfterpayCountries();
            const names                         = { US: 'United States', AU: 'Australia', NZ: 'New Zealand', GB: 'United Kingdom', CA: 'Canada' };
            const countryItems = countries.map(country => `<li data-value="${this.escapeHtml(country)}">${this.escapeHtml($t(names[country] || country))}</li>`).join('');
            let that = this;
            const paymentData = window.checkoutConfig .payment .airwallex_payments ;
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            let html = `
                <div style="font-weight: 700;">` + $t('Choose your Afterpay account region') + `</div>
                <div style="margin: 10px 0;">` + $t('If you don’t have an account yet, choose the region that you will create your account from.') + `</div>
                <div class="awx-selector-container">
                    <div class="input-icon">
                        <img src="` + require.toUrl('Airwallex_Payments/assets/select-arrow.svg') + `" alt="arrow" />
                    </div>
                    <div>
                        <input readonly type="text" placeholder="` + $t('Afterpay account region') + `" />
                    </div>
                    <div class="countries" style="display: none">
                        <ul>
                            ${countryItems}
                        </ul>
                    </div>
                </div>
            `;
            const countryToCurrency = paymentData.afterpay_support_countries ;
            let country = localStorage.getItem(this.afterpayCountryKey);
            if (!country || countries.indexOf(country) === -1) {
                container.html(html);
                this.disableCheckoutButton(this.code);
            } else {
                const targetCurrency = countryToCurrency[country];
                if (targetCurrency === paymentData.quote_currency_code ) {
                    container.html(html);
                    this.hideYouPay();
                    this.enableCheckoutButton(this.code);
                } else {
                    await this.displaySwitcher(html, expressData, targetCurrency, 'Afterpay');
                }
            }
            if (!this.isRenderCurrent(version)) return;
            let $li = container.find('.awx-selector-container li');
            let $input = container.find('.awx-selector-container input');
            $li.each(function (                 ) {
                let country = localStorage.getItem(that.afterpayCountryKey);
                if ($(this).data("value") === country) {
                    $input.val($(this).text());
                    that.enableCheckoutButton(that.code);
                }
            });
            let showCountries = function () {
                container.find('.awx-selector-container .countries').fadeIn(300);
                let country = localStorage.getItem(that.afterpayCountryKey);
                if (country) {
                    container.find('.awx-selector-container li').each(function (                 ) {
                        $(this).removeClass("selected");
                        if ($(this).data("value") === country) {
                            $(this).addClass("selected");
                        }
                    });
                }
            };
            $input.off('focus').on('focus', showCountries);
            container.find('.awx-selector-container .input-icon').off('click').on('click', showCountries);
            $input.off('blur').on('blur', function () {
                container.find('.awx-selector-container .countries').fadeOut(300);
            });
            $li.off('click').on('click', function (                 ) {
                const country = String($(this).data("value"));
                if (countries.indexOf(country) === -1) return;
                void that.selectCurrency(that.afterpayCountryKey, country, async (selectionVersion        ) => {
                    await that.showAfterpayCountries(expressData);
                    if (that.isRenderCurrent(selectionVersion)) that.selectedAfterpayCountry = country;
                });
            });
        },
    });
});
