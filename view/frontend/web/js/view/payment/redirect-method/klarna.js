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
            code: 'airwallex_payments_klarna',
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

            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            if (Object.keys(paymentData.klarna_support_countries).indexOf(quote.billingAddress().countryId) === -1) {
                const msg = $t('Klarna is not available in your country. Please change your billing address to a %1compatible country%2 or choose a different payment method.')
                    .replace('%1', "<a target='_blank' class='awx-compatible-country-link' href='https://help.airwallex.com/hc/en-gb/articles/9514119772047-What-countries-can-I-use-Klarna-in'>")
                    .replace('%2', "</a>");
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }

            const targetCurrency = paymentData.klarna_support_countries[quote.billingAddress().countryId];
            if (paymentData.quote_currency_code === targetCurrency) {
                container.html('');
                this.enableCheckoutButton(this.code);
                return;
            }

            const availableCurrencies = paymentData.available_currencies ? JSON.parse(paymentData.available_currencies) : [];
            if (availableCurrencies.indexOf(paymentData.quote_currency_code) === -1) {
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', 'Klarna')
                    .replace('%2', paymentData.quote_currency_code);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }
            if (availableCurrencies.indexOf(targetCurrency) === -1) {
                const msg = $t('%1 is not available in %2 for your billing country. Please use a different payment method to complete your purchase.')
                    .replace('%1', 'Klarna')
                    .replace('%2', targetCurrency);
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }

            const expressData = await this.fetchExpressData();
            await this.displaySwitcher('', expressData, targetCurrency, 'Klarna');
        },
    });
});
