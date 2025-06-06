define([
    "Airwallex_Payments/js/view/payment/redirect-method",
    "ko",
    "jquery",
    'Magento_Checkout/js/model/quote',
    'Airwallex_Payments/js/view/payment/utils',
    'mage/translate'
], function (Component, ko, $, quote, utils, $t) {
    "use strict";

    return Component.extend({
        defaults: {
            code: 'airwallex_payments_afterpay',
            template: "Airwallex_Payments/payment/redirect-method",
        },

        initialize() {
            this._super();
            quote.billingAddress.subscribe((newValue) => {
                this.renderPayment(newValue, 'billingAddress');
            });

            quote.paymentMethod.subscribe((newValue) => {
                this.renderPayment(newValue, 'paymentMethod');
            });

            quote.totals.subscribe((newValue) => {
                this.renderPayment(newValue, 'totals');
            });
        },

        async loadPayment() {
            if (!this.isMethodChecked(this.code)) {
                return;
            }

            this.hideYouPay();

            const container = $(`.${this.index} .awx-redirect-method-footer`);

            const entity = await this.fetchEntity();
            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const entityToCurrency = paymentData.afterpay_support_entity_to_currency;
            const countryToCurrency = paymentData.afterpay_support_countries;
            const quoteCountryId = quote.billingAddress() ? quote.billingAddress().countryId : '';

            if (!entityToCurrency[entity]) {
                $(container).html(utils.awxAlert($t('Invalid merchant entity.')));
                this.disableCheckoutButton(this.code);
                return;
            }

            const availableCurrencies = paymentData.available_currencies ? JSON.parse(paymentData.available_currencies) : [];

            if (!availableCurrencies.length) {
                if (entityToCurrency[entity].indexOf(paymentData.quote_currency_code) !== -1) {
                    $(container).html('');
                    this.enableCheckoutButton(this.code);
                    return;
                }
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', 'Afterpay')
                    .replace('%2', paymentData.quote_currency_code);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }

            if (entityToCurrency[entity].indexOf(paymentData.quote_currency_code) !== -1 && (entity !== 'AIRWALLEX_HK' || (countryToCurrency[quoteCountryId] === paymentData.quote_currency_code && entity === 'AIRWALLEX_HK'))) {
                $(container).html('');
                this.enableCheckoutButton(this.code);
                return;
            }

            if (availableCurrencies.indexOf(paymentData.quote_currency_code) === -1) {
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', 'Afterpay')
                    .replace('%2', paymentData.quote_currency_code);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }

            const expressData = await this.fetchExpressData();

            let targetCurrency;
            if (countryToCurrency[quoteCountryId]) {
                targetCurrency = countryToCurrency[quoteCountryId];
                if (entityToCurrency[entity].indexOf(targetCurrency) === -1) {
                    targetCurrency = '';
                }
            }

            if (!targetCurrency && entityToCurrency[entity].length === 1) {
                targetCurrency = entityToCurrency[entity][0];
            }

            if (targetCurrency) {
                await this.displaySwitcher('', expressData, targetCurrency, 'Afterpay');
                return;
            }

            await this.showAfterpayCountries(expressData);
        },

        async showAfterpayCountries(expressData) {
            let that = this;

            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const container = $(`.${this.index} .awx-redirect-method-footer`);

            let html = `
                <div style="font-weight: 700;">` + $t('Choose your Afterpay account region') + `</div>
                <div style="margin: 10px 0;">` + $t('If you don’t have an account yet, choose the region that you will create your account from.') + `</div>
                <div class="awx-afterpay-countries">
                    <div class="input-icon">
                        <img src="` + require.toUrl('Airwallex_Payments/assets/select-arrow.svg') + `" alt="arrow" />
                    </div>
                    <div>
                        <input type="text" placeholder="Afterpay account region" />
                    </div>
                    <div class="countries" style="display: none">
                        <ul>
                            <li data-value="US">` + $t('United States') + `</li>
                            <li data-value="AU">` + $t('Australia') + `</li>
                            <li data-value="NZ">` + $t('New Zealand') + `</li>
                            <li data-value="GB">` + $t('United Kingdom') + `</li>
                            <li data-value="CA">` + $t('Canada') + `</li>
                        </ul>
                    </div>
                </div>
            `;

            const countryToCurrency = paymentData.afterpay_support_countries;
            let country = localStorage.getItem(this.afterpayCountryKey);
            if (!country || !countryToCurrency[country]) {
                container.html(html);
                this.disableCheckoutButton(this.code);
            } else {
                const targetCurrency = countryToCurrency[country];
                if (targetCurrency === paymentData.quote_currency_code) {
                    container.html(html);
                    this.hideYouPay();
                    this.enableCheckoutButton(this.code);
                } else {
                    await this.displaySwitcher(html, expressData, targetCurrency, 'Afterpay');
                }
            }

            let $li = $(".awx-afterpay-countries li");
            let $input = $(".awx-afterpay-countries input");
            $li.each(function () {
                let country = localStorage.getItem(that.afterpayCountryKey);
                if ($(this).data("value") === country) {
                    $input.val($(this).html());
                    that.enableCheckoutButton(that.code);
                }
            });
            let showCountries = function () {
                $(".awx-afterpay-countries .countries").fadeIn(300);
                let country = localStorage.getItem(that.afterpayCountryKey);
                if (country) {
                    $(".awx-afterpay-countries li").each(function () {
                        $(this).removeClass("selected");
                        if ($(this).data("value") === country) {
                            $(this).addClass("selected");
                        }
                    });
                }
            };
            $input.off('focus').on('focus', showCountries);
            $('.awx-afterpay-countries .input-icon').off('click').on('click', showCountries);
            $input.off('blur').on('blur', function () {
                $(".awx-afterpay-countries .countries").fadeOut(300);
            });
            $li.off('click').on('click', function () {
                let $body = $('body');
                $body.trigger('processStart');
                localStorage.setItem(that.afterpayCountryKey, $(this).data("value"));
                that.showAfterpayCountries(expressData);
                $body.trigger('processStop');
            });
        },
    });
});
