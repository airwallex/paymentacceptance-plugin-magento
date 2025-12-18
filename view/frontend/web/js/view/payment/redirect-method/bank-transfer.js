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
            code: 'airwallex_payments_bank_transfer',
            template: "Airwallex_Payments/payment/redirect-method",
        },

        async loadPayment() {
            if (!this.isMethodChecked(this.code)) {
                return;
            }
            this.hideYouPay();

            const container = $(`.${this.index} .awx-redirect-method-footer`);
            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const availableCurrencies = paymentData.available_currencies || [];

            if (availableCurrencies.length === 0) {
                if (Object.values(paymentData.bank_transfer_support_country_to_currency_collection).indexOf(paymentData.quote_currency_code) !== -1) {
                    container.html('');
                    this.enableCheckoutButton(this.code);
                    return;
                }
            }

            if (availableCurrencies.indexOf(paymentData.quote_currency_code) === -1) {
                const msg = $t('Bank transfer is not available in this currency yet. Please change your currency to a %1compatible currency%2 or choose a different payment method.')
                    .replace("%1", "<a target='_blank' class='awx-compatible-country-link' href='https://www.airwallex.com/docs/payments__global__bank-transfer-beta'>")
                    .replace("%2", "</a>");
                container.html(utils.awxAlert(msg));
                this.disableCheckoutButton(this.code);
                return;
            }

            let countryID = quote.billingAddress() ? quote.billingAddress().countryId : '';
            let targetCurrency = paymentData.bank_transfer_support_country_to_currency_collection[countryID];

            if (targetCurrency === paymentData.quote_currency_code) {
                container.html('');
                this.enableCheckoutButton(this.code);
                return;
            }

            const expressData = await this.fetchExpressData();
            if (targetCurrency) {
                await this.displaySwitcher('', expressData, targetCurrency, 'Bank Transfer');
                return;
            }

            await this.showBankTransferCurrencies(expressData);
        },

        async displayYouPay(html, expressData, targetCurrency, brand) {
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            const switchers = await this.switcher(expressData.quote_currency_code, targetCurrency, expressData.grand_total);
            container.html(html);
            this.showYouPay(switchers);
            this.enableCheckoutButton(this.code);
        },

        async showBankTransferCurrencies(expressData) {
            const container = $(`.${this.index} .awx-redirect-method-footer`);
            let that = this;
            const paymentData = window.checkoutConfig.payment.airwallex_payments;
            const countryToCurrencyCollection = paymentData.bank_transfer_support_country_to_currency_collection;
            let selectedCurrency = localStorage.getItem(that.bankTransferCurrencyKey);
            if (Object.values(countryToCurrencyCollection).indexOf(selectedCurrency) === -1) {
                localStorage.setItem(that.bankTransferCurrencyKey, "USD");
                selectedCurrency = 'USD';
            }

            const currencyToRegion = {
                USD: 'us',
                SGD: 'sg',
                EUR: 'eu',
                GBP: 'gb',
                HKD: 'hk'
            };

            let ulContentHtml = '';
            for (let key in currencyToRegion) {
                ulContentHtml += '<li data-value="' + key + '"><div class="flag-currency-container"><img class="flag" src="' + require.toUrl('Airwallex_Payments/assets/' + currencyToRegion[key] + '.svg') + '" alt=""> ' + key + '</div></li>';
            }

            let html = `
                <div class="awx-bank-transfer-currencies">
                    <div>Payment currency</div>
                    <div>
                        <div class="select">
                            <span style="font-weight: 700; color: black; display: flex; align-items: center;">
                                <img class="flag" src="` + require.toUrl('Airwallex_Payments/assets/' + currencyToRegion[selectedCurrency] + '.svg') + `" alt=""> ` + selectedCurrency + `
                            </span>
                            <img src="` + require.toUrl('Airwallex_Payments/assets/select-arrow.svg') + `" alt="">
                        </div>
                        <ul style="display: none" class="countries">
                          ` + ulContentHtml + `
                        </ul>
                    </div>
                </div>
                <div style="margin-bottom: 8px;">Confirm your order and payment currency to receive the transfer instructions.</div>
            `;

            if (selectedCurrency === paymentData.quote_currency_code) {
                container.html(html);
                this.hideYouPay();
                this.enableCheckoutButton(this.code);
            } else {
                await that.displayYouPay(html, expressData, selectedCurrency, 'Bank Transfer');
            }

            $('.airwallex_payments_bank_transfer .awx-bank-transfer-currencies .select').off('click').on('click', function () {
                const ul = $('.airwallex_payments_bank_transfer .awx-bank-transfer-currencies ul');
                const select = $('.airwallex_payments_bank_transfer .awx-bank-transfer-currencies .select');
                if (ul.css('display') === 'none') {
                    ul.show();
                    select.css('border', '2px solid rgba(97, 47, 255, 1)');
                } else {
                    ul.hide();
                    select.css('border', '1px solid rgba(232, 234, 237, 1)');
                }
            });

            $('.airwallex_payments_bank_transfer .awx-bank-transfer-currencies ul li').click(async function () {
                let $body = $('body');
                $body.trigger('processStart');
                localStorage.setItem(that.bankTransferCurrencyKey, $(this).data("value"));
                await that.showBankTransferCurrencies(expressData);
                $body.trigger('processStop');
            });
        }
    });
});
