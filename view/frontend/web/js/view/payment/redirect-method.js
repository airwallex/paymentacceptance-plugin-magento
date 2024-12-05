define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/checkout-data',
    'ko',
    'jquery',
    'mage/url',
    'mage/storage',
    'Magento_Ui/js/model/messageList',
    'Airwallex_Payments/js/view/payment/utils',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function (
    Component,
    selectPaymentMethodAction,
    checkoutData,
    ko,
    $,
    urlBuilder,
    storage,
    globalMessageList,
    utils,
    additionalValidators,
    redirectOnSuccessAction,
    addressHandler,
    quote,
    customerData,
    modal,
    $t
) {
    'use strict';

    return Component.extend({
        type: 'redirect',
        redirectAfterPlaceOrder: false,
        validationError: ko.observable(),
        isShowBillingAddress: ko.observable(true),
        iframeSelector: "._active .qrcode-payment .iframe",
        qrcodeSelector: "._active .qrcode-payment .qrcode",
        expressData: {},
        oldBillingAddress: '',
        afterpayCountryKey: 'awx_afterpay_country',

        defaults: {
            timer: null,
            template: 'Airwallex_Payments/payment/redirect-method'
        },

        isAirwallexPayment(newMethod) {
            return newMethod && newMethod.method.indexOf('airwallex_') === 0;
        },

        isSwitcherMethod() {
            return this.isMethodChecked('afterpay') || this.isMethodChecked('klarna');
        },

        initialize: async function () {
            this._super();
            if (!window.awxMonitorBillingAddress) {
                window.awxMonitorBillingAddress = true;
                quote.billingAddress.subscribe(async (newAddress) => {
                    if (JSON.stringify(newAddress) === JSON.stringify(this.oldBillingAddress)) {
                        return;
                    }
                    this.oldBillingAddress = newAddress;
                    $(".awx-afterpay-countries-component").html('');
                    this.validationError('');
                    this.hideYouPay();
                    await this.testPaymentMethod();
                    $('body').trigger('processStop');
                });
                quote.paymentMethod.subscribe(async (newMethod) => {
                    if (this.isAirwallexPayment(newMethod)) {
                        $(".totals.charge").hide();
                        if (this.isMethodChecked('afterpay')) {
                            $(".awx-billing-confirm-tip").hide();
                        } else {
                            $(".awx-billing-confirm-tip").show();
                        }
                    } else {
                        $(".totals.charge").show();
                    }
                    $(".awx-afterpay-countries-component").html('');
                    this.hideYouPay();
                    this.validationError('');
                    await this.testPaymentMethod();
                    $('body').trigger('processStop');
                });
                // await this.testPaymentMethod();
                // $('body').trigger('processStop');
            }
        },

        initObservable: function () {
            this.code = this.index;
            return this._super();
        },

        showPayafterCountries() {
            let that = this;
            let html = `
                <div style="font-weight: 700;">Choose your Afterpay account region</div>
                <div style="margin: 10px 0;">If you don’t have an account yet, choose the region that you will create your account from. </div>
                <div class="awx-afterpay-countries">
                <div class="input-icon">
                    <svg
                    width="12"
                    height="7"
                    viewBox="0 0 12 7"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6 3.83294L9.5405 0.293238C9.93157 -0.0977462 10.5656 -0.0977462 10.9567 0.293238C11.3478 0.684223 11.3478 1.31813 10.9567 1.70912L6.7081 5.95676C6.31703 6.34775 5.68297 6.34775 5.2919 5.95676L1.0433 1.70912C0.652232 1.31813 0.652232 0.684223 1.0433 0.293238C1.43438 -0.0977462 2.06843 -0.0977462 2.4595 0.293238L6 3.83294Z"
                        fill="#68707A"
                    />
                    </svg>
                </div>
                <div>
                    <input type="text" placeholder="Afterpay account region" />
                </div>
                <div class="countries" style="display: none">
                    <ul>
                    <li data-value="US">United States</li>
                    <li data-value="AU">Australia</li>
                    <li data-value="NZ">New Zealand</li>
                    <li data-value="UK">United Kingdom</li>
                    <li data-value="CA">Canada</li>
                    </ul>
                </div>
                </div>
            `;

            $(".awx-afterpay-countries-component").html(html);

            let $li = $(".awx-afterpay-countries li");
            let $input = $(".awx-afterpay-countries input");
            $li.each(function () {
                let country = localStorage.getItem(that.afterpayCountryKey);
                if ($(this).data("value") === country) {
                    $(".awx-afterpay-countries input").val($(this).html());
                    that.activeCheckoutButton();
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
            $('.awx-afterpay-countries-component .input-icon').off('click').on('click', showCountries);
            $input.off('blur').on('blur', function () {
                $(".awx-afterpay-countries .countries").fadeOut(300);
            });
            $li.off('click').on('click', async function () {
                let $body = $('body');
                that.validationError('');
                $('.awx-you-pay').hide();
                $body.trigger('processStart');
                $(".awx-afterpay-countries input").val($(this).html());
                localStorage.setItem(that.afterpayCountryKey, $(this).data("value"));
                $(".awx-afterpay-countries li").each(function () {
                    $(this).removeClass("selected");
                });
                $(".awx-afterpay-countries .countries").fadeOut(300);
                const countryToCurrency = window.checkoutConfig.payment.airwallex_payments.afterpay_support_countries;
                let country = $(this).data("value");
                let targetCurrency = countryToCurrency[country];
                let switchers = await that.switcher(that.expressData.quote_currency_code, targetCurrency, that.expressData.grand_total);
                that.validationError(that.switcherTip(targetCurrency, 'afterpay'));
                that.showYouPay(switchers);
                that.activeCheckoutButton();
                $body.trigger('processStop');
            });
        },

        async switcher(payment_currency, target_currency, amount) {
            let res = await storage.post(urlBuilder.build('rest/V1/airwallex/currency/switcher'), JSON.stringify({
                'payment_currency': payment_currency,
                'target_currency': target_currency,
                'amount': amount,
            }), undefined, 'application/json', {});
            return JSON.parse(res);
        },

        getDisplayName() {
            let name = this.getCode();
            let arr = {
                "airwallex_payments_alipaycn": "Alipay CN",
                "airwallex_payments_alipayhk": "Alipay HK",
                "airwallex_payments_pay_now": "PayNow",
                "airwallex_payments_dana": "DANA",
                "airwallex_payments_kakaopay": "Kakao Pay",
                "airwallex_payments_tng": "Touch 'n Go",
                "airwallex_payments_klarna": "Klarna",
                "airwallex_payments_afterpay": "Afterpay",
            };
            return arr[name];
        },

        isShowQRTip() {
            return ['airwallex_payments_klarna', 'airwallex_payments_afterpay',].indexOf(this.code) === -1;
        },

        hideBillingAddress() {
            $('.' + this.getCode() + ' button.editing').show();
            $('.' + this.getCode() + ' .payment-method-billing-address').hide();
            let refreshSelector = "#" + this.getCode() + '-button span';
            $(refreshSelector).text($t('Refresh QR code'));
        },

        async fetchExpressData() {
            let url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
            const resp = await storage.get(url, undefined, 'application/json', {});
            this.expressData = JSON.parse(resp);
        },

        async fetchEntity() {
            let accountUrl = urlBuilder.build('rest/V1/airwallex/account');
            const accountResp = await storage.get(accountUrl, undefined, 'application/json', {});
            let accountData = JSON.parse(accountResp);
            return accountData.owningEntity;
        },

        async processAfterpay() {
            let that = this;
            this.activeCheckoutButton();
            let entity = await this.fetchEntity();
            if (entity !== 'AIRWALLEX_HK') {
                $(".awx-billing-confirm-tip").hide();
            } else {
                $(".awx-billing-confirm-tip").show();
            }
            const entityToCurrency = window.checkoutConfig.payment.airwallex_payments.afterpay_support_entity_to_currency;
            const countryToCurrency = window.checkoutConfig.payment.airwallex_payments.afterpay_support_countries;
            if (!entityToCurrency[entity]) {
                throw new Error("Afterpay is not available in your country.");
            }
            if (entityToCurrency[entity].indexOf(window.checkoutConfig.quoteData.quote_currency_code) !== -1) {
                this.validationError('');
                return true;
            }
            await this.fetchExpressData();
            if (entityToCurrency[entity].indexOf(this.expressData.base_currency_code) !== -1) {
                this.validationError(this.switcherTip(this.expressData.base_currency_code, 'afterpay'));
                this.showYouPay({
                    payment_currency: this.expressData.quote_currency_code,
                    target_currency: this.expressData.base_currency_code,
                    client_rate: (1 / this.expressData.base_to_quote_rate).toFixed(4),
                    target_amount: parseFloat(this.expressData.base_grand_total).toFixed(2),
                });
                return true;
            }

            let targetCurrency;
            let cId = quote.billingAddress() ? quote.billingAddress().countryId : '';
            if (cId === 'GB') cId = 'UK';
            if (countryToCurrency[cId]) {
                targetCurrency = countryToCurrency[cId];
                if (entityToCurrency[entity].indexOf(targetCurrency) === -1) {
                    targetCurrency = '';
                }
            }
            let $body = $('body');
            if (!targetCurrency) {
                this.showPayafterCountries();
                let country = localStorage.getItem(this.afterpayCountryKey);
                if (!country || country === 'undefined') {
                    if (entityToCurrency[entity].length !== 1) {
                        if (!localStorage.getItem(this.afterpayCountryKey)) {
                            this.disableCheckoutButton();
                        }
                        $body.trigger('processStop');
                        return false;
                    }
                    targetCurrency = entityToCurrency[entity][0];
                } else {
                    targetCurrency = countryToCurrency[country];
                }
            }

            let switchers = await this.switcher(that.expressData.quote_currency_code, targetCurrency, that.expressData.grand_total);
            this.validationError(this.switcherTip(targetCurrency, 'afterpay'));
            this.showYouPay(switchers);
            $body.trigger('processStop');
            return true;
        },

        activeCheckoutButton() {
            $('.airwallex._active .checkout').removeClass('disabled');
        },

        disableCheckoutButton() {
            $('.airwallex._active .checkout').addClass('disabled');
        },

        testPaymentMethod: async function () {
            let $body = $('body');
            $body.trigger('processStart');

            if (this.isSwitcherMethod()) {
                $(".totals.charge").hide();
                if (!quote.billingAddress()) {
                    $body.trigger('processStop');
                    this.disableCheckoutButton();
                    return false;
                }

                if (this.isMethodChecked('afterpay')) {
                    return await this.processAfterpay();
                }

                let sourceCurrency = window.checkoutConfig.quoteData.quote_currency_code;
                let countries = window.checkoutConfig.payment.airwallex_payments.klarna_support_countries;
                if (Object.keys(countries).indexOf(quote.billingAddress().countryId) === -1) {
                    let msg = "Klarna is not available in your country. Please change your billing address to a " +
                        "<a target='_blank' style='color: rgba(66, 71, 77, 1); font-weight: 800; text-decoration: underline;' href='https://help.airwallex.com/hc/en-gb/articles/9514119772047-What-countries-can-I-use-Klarna-in'>compatible country</a> or choose a different payment method.";
                    this.validationError(utils.awxAlert(msg));
                    this.disableCheckoutButton();
                    return false;
                }
                let targetCurrency = countries[quote.billingAddress().countryId];
                let currencies = JSON.parse(window.checkoutConfig.payment.airwallex_payments.available_currencies);
                if (currencies.indexOf(sourceCurrency) === -1 || currencies.indexOf(targetCurrency) === -1) {
                    let msg = "Klarna is not available in " + sourceCurrency
                        + " for your billing country. Please use a different payment method to complete your purchase.";
                    this.validationError(utils.awxAlert(msg));
                    this.disableCheckoutButton();
                    return false;
                }
                if (currencies.indexOf(targetCurrency) === -1) {
                    let msg = "Klarna is not available in " + targetCurrency
                        + " for your billing country. Please use a different payment method to complete your purchase.";
                    this.validationError(utils.awxAlert(msg));
                    this.disableCheckoutButton();
                    return false;
                }
                this.activeCheckoutButton();
                if (sourceCurrency === targetCurrency) {
                    this.validationError('');
                    return true;
                }
                this.validationError(this.switcherTip(targetCurrency, 'klarna'));

                await this.fetchExpressData();

                if (targetCurrency === this.expressData.base_currency_code) {
                    this.showYouPay({
                        payment_currency: this.expressData.quote_currency_code,
                        target_currency: targetCurrency,
                        client_rate: (1 / this.expressData.base_to_quote_rate).toFixed(4),
                        target_amount: parseFloat(this.expressData.base_grand_total).toFixed(2),
                    });
                    return true;
                }
                let that = this;
                let switchers = await this.switcher(that.expressData.quote_currency_code, targetCurrency, that.expressData.grand_total);
                this.showYouPay(switchers);
            }
            return true;
        },

        switcherTip(targetCurrency, method) {
            let brand = 'Klarna';
            if (method.toLowerCase() === 'afterpay' || method === 'airwallex_payments_afterpay') brand = 'Afterpay';
            return "<span style='color: rgba(26, 29, 33, 1); font-weight: 600;'>We have converted the currency to "
                + targetCurrency + " so you can use " + brand + ".</span>";
        },

        showYouPay(switchers = {}) {
            let youPayElement = '.awx-you-pay';
            if (!$(youPayElement).length) {
                $(".table-totals tbody").append(`
                    <tr class="awx-you-pay"></tr>
                `);
            }
            $(youPayElement).html(`
                <th class="mark" scope="row" style="padding-top: 33px;">
                    <span style="font-size: 1.8rem; font-weight: 600;">` + $t('You Pay') + `</strong>
                </th>
                <td class="amount">
                    <div class="switcher-tip">
                        <div style="color: rgba(108, 116, 127, 1); margin-right: 5px;">1 ${switchers.payment_currency} = ${switchers.client_rate} ${switchers.target_currency}</div>
                        <svg width="12" height="24" viewBox="0 0 12 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <line x1="6.5" y1="2.18557e-08" x2="6.5" y2="6" stroke="#E8EAED"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.8751 12.006C11.2227 11.8469 11.641 11.9755 11.836 12.3134C12.0431 12.6721 11.9202 13.1308 11.5615 13.3379L9.93769 14.2754C9.57897 14.4825 9.12028 14.3596 8.91317 14.0009L7.97567 12.3771C7.76857 12.0184 7.89147 11.5597 8.25019 11.3526C8.60891 11.1455 9.0676 11.2684 9.27471 11.6271L9.36849 11.7895C9.25886 10.0245 7.79267 8.62695 6.00007 8.62695C5.0122 8.62695 4.12347 9.05137 3.50626 9.72782L2.44482 8.66638C3.33417 7.71884 4.598 7.12695 6.00007 7.12695C8.69245 7.12695 10.8751 9.30957 10.8751 12.002C10.8751 12.0033 10.8751 12.0047 10.8751 12.006ZM1.12576 12.0887L1.12513 12.0891C0.766406 12.2962 0.307713 12.1733 0.100606 11.8146C-0.106501 11.4559 0.0164058 10.9972 0.375125 10.7901L1.99892 9.85256C2.35764 9.64545 2.81633 9.76836 3.02344 10.1271L3.96094 11.7509C4.16805 12.1096 4.04514 12.5683 3.68642 12.7754C3.3277 12.9825 2.86901 12.8596 2.6619 12.5009L2.66152 12.5002C2.90238 14.1279 4.30533 15.377 6 15.377C6.85293 15.377 7.63196 15.0606 8.22613 14.5387L9.28834 15.6009C8.42141 16.3935 7.26716 16.877 6 16.877C3.3366 16.877 1.17206 14.7411 1.12576 12.0887Z" fill="#B0B6BF"/>
                            <line x1="6.5" y1="18" x2="6.5" y2="24" stroke="#E8EAED"/>
                        </svg>
                    </div>
                    <div class="awx-amount">${switchers.target_currency} ${switchers.target_amount}</div>
                </td>
            `);
            $(youPayElement).show();
        },

        hideYouPay() {
            $('.awx-you-pay').hide();
        },

        showBillingAddress() {
            $('.' + this.getCode() + ' button.editing').hide();
            $('.' + this.getCode() + ' .payment-method-billing-address').show();
            $(this.iframeSelector).hide();
            $(this.qrcodeSelector).hide();
            let refreshSelector = "#" + this.getCode() + '-button span';
            $(refreshSelector).text($t('Confirm'));
        },

        isMethodChecked(method) {
            if (!method) {
                return false;
            }
            if (!quote.paymentMethod() || !quote.paymentMethod().method) return false;
            return quote.paymentMethod().method === 'airwallex_payments_' + method.toLowerCase();
        },

        placeOrder: async function (data, event) {
            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                let $body = $('body');
                $body.trigger('processStart');

                try {
                    if (!await this.testPaymentMethod()) {
                        $body.trigger('processStop');
                        return;
                    }
                    // this.validationError('');
                    const payload = {
                        cartId: quote.getQuoteId(),
                        paymentMethod: {
                            method: quote.paymentMethod().method,
                            additional_data: {
                                "afterpay_country": localStorage.getItem(this.afterpayCountryKey)
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
                    let nextAction = JSON.parse(intentResponse.next_action);
                    // url qrcode_url qrcode
                    if (this.isSwitcherMethod()) {
                        utils.clearDataAfterPay({}, customerData);
                        location.href = nextAction.url;
                        return;
                    }
                    $(this.iframeSelector).html('').hide();
                    $(this.qrcodeSelector).html('').hide();
                    $("._active .qrcode-payment").css('display', 'flex');
                    this.hideBillingAddress();
                    if (['airwallex_payments_pay_now'].indexOf(this.code) === -1) {
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
                    if (this.timer) clearInterval(this.timer);
                    this.timer = setInterval(async () => {
                        let res = await this.getIntent(intentResponse.intent_id);
                        let response = JSON.parse(res);
                        if (response.paid && response.is_order_status_changed) {
                            utils.clearDataAfterPay(response, customerData);
                            redirectOnSuccessAction.execute();
                        }
                    }, 2500);
                } catch (e) {
                    console.log(e);
                    if (e.responseJSON && e.responseJSON.message) {
                        this.validationError($t(e.responseJSON.message));
                    } else {
                        this.validationError($t('Something went wrong while processing your request. Please try again.'));
                    }
                    $body.trigger('processStop');
                    return;
                } finally {
                    if (!this.isSwitcherMethod()) {
                        $body.trigger('processStop');
                    }
                }
                return true;
            }

            return false;
        },

        async getIntent(intentId) {
            let requestUrl = urlBuilder.build('rest/V1/airwallex/payments/intent?intent_id=' + intentId);
            return storage.get(requestUrl, undefined, 'application/json', {});
        },
    });
});
