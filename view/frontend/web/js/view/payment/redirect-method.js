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

        defaults: {
            timer: null,
            template: 'Airwallex_Payments/payment/redirect-method'
        },

        initialize: async function () {
            this._super();
            if (!window.awxMonitorBillingAddress) {
                quote.billingAddress.subscribe((newAddress) => {
                    if (window.awxBillingAddress === JSON.stringify(newAddress)) return;
                    this.hideYouPay();
                    this.testPaymentMethod();
                    window.awxBillingAddress = JSON.stringify(newAddress);
                });
                window.awxMonitorBillingAddress = true;
            }
        },

        initObservable: function () {
            this.code = this.index;
            return this._super();
        },

        getDisplayName() {
            let name = this.getCode();
            let arr = {
                'airwallex_payments_alipaycn': 'Alipay CN',
                'airwallex_payments_alipayhk': 'Alipay HK',
                'airwallex_payments_pay_now': 'PayNow',
                'airwallex_payments_dana': 'DANA',
                'airwallex_payments_kakaopay': 'Kakao Pay',
                'airwallex_payments_tng': 'Touch \'n Go',
                'airwallex_payments_klarna': 'Klarna',
            };
            return arr[name];
        },

        isShowQRTip() {
            return ['airwallex_payments_klarna'].indexOf(this.code) === -1;
        },

        hideBillingAddress() {
            $('.' + this.getCode() + ' button.editing').show();
            $('.' + this.getCode() + ' .payment-method-billing-address').hide();
            $("#" + this.getCode() + '-button span').text($t('Refresh QR code'));
        },

        selectPaymentMethod:  function () {
            this.hideYouPay();
            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.item.method);
            this.validationError('');
            this.testPaymentMethod();
            return true;
        },

        testPaymentMethod: async function () {
            let $body = $('body');
            if (this.isKlarnaChecked()) {
                this.validationError('');
                let countries = window.checkoutConfig.payment.airwallex_payments.klarna_support_countries;
                if (!quote.billingAddress()) {
                    this.validationError($t('Billing address is required.'));
                    $body.trigger('processStop');
                    return false;
                }
                if (Object.keys(countries).indexOf(quote.billingAddress().countryId) === -1) {
                    let msg = "Klarna is not available in your country. Please change your billing address to " +
                        "<a target='_blank' class='awx-danger' href='https://help.airwallex.com/hc/en-gb/articles/9514119772047-What-countries-can-I-use-Klarna-in'>a compatible country</a> or choose a different payment method.";
                    this.validationError(msg);
                    return false;
                }
                let targetCurrency = countries[quote.billingAddress().countryId];
                let currencies = JSON.parse(window.checkoutConfig.payment.airwallex_payments.available_currencies);
                if (currencies.indexOf(targetCurrency) === -1) {
                    let msg = "Klarna is not available in " + window.checkoutConfig.quoteData.quote_currency_code
                        + " for your billing country. Please use a different payment method to complete your purchase.";
                    this.validationError(msg);
                    return false;
                }
                if (window.checkoutConfig.quoteData.quote_currency_code === targetCurrency) return true;
                if (targetCurrency === window.checkoutConfig.quoteData.base_currency_code && $(".totals.charge").length) return true;
                let msg = "<span style='color: #1e1e1e;'>Klarna is not available in "
                    + window.checkoutConfig.quoteData.quote_currency_code + " for your billing country. We have converted your total to "
                    + targetCurrency + " for you to complete your payment.</span>";
                this.validationError(msg);

                let url = urlBuilder.build('rest/V1/airwallex/payments/express-data');
                const resp = await storage.get(url, undefined, 'application/json', {});
                let expressData = JSON.parse(resp);

                if (targetCurrency === expressData.base_currency_code) {
                    this.showYouPay({
                        payment_currency: expressData.quote_currency_code,
                        target_currency: targetCurrency,
                        client_rate: (1/expressData.base_to_quote_rate).toFixed(4),
                        target_amount: parseFloat(expressData.base_grand_total).toFixed(2),
                    });
                    return true;
                }
                let switcher = await storage.post(urlBuilder.build('rest/V1/airwallex/currency/switcher'), JSON.stringify({
                    'payment_currency': expressData.quote_currency_code,
                    'target_currency': targetCurrency,
                    'amount': expressData.grand_total,
                }), undefined, 'application/json', {});
                let switchers = JSON.parse(switcher);
                this.showYouPay(switchers);
            }
            return true;
        },

        showYouPay(switchers = {}) {
            if (!$('.awx-you-pay').length) {
                $(".table-totals tbody").append(`
                    <tr class="awx-you-pay"></tr>
                `);
            }
            $('.awx-you-pay').html(`
                <th class="mark" scope="row">
                    <strong style="font-size: 1.8rem">` + $t('You Pay') + `</strong>
                </th>
                <td class="amount">
                    <div class="switcher-tip">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path fillRule="evenodd" clipRule="evenodd" d="M10.8751 6.00603C11.2227 5.84685 11.641 5.97553 11.836 6.31338C12.0431 6.6721 11.9202 7.13079 11.5615 7.33789L9.93769 8.27539C9.57897 8.4825 9.12028 8.3596 8.91317 8.00088L7.97567 6.37708C7.76857 6.01836 7.89147 5.55967 8.25019 5.35256C8.60891 5.14545 9.0676 5.26836 9.27471 5.62708L9.36849 5.78951C9.25886 4.02452 7.79267 2.62695 6.00007 2.62695C5.0122 2.62695 4.12347 3.05137 3.50626 3.72782L2.44482 2.66638C3.33417 1.71884 4.598 1.12695 6.00007 1.12695C8.69245 1.12695 10.8751 3.30957 10.8751 6.00195C10.8751 6.00331 10.8751 6.00467 10.8751 6.00603ZM1.12576 6.08873L1.12513 6.0891C0.766406 6.2962 0.307713 6.1733 0.100606 5.81458C-0.106501 5.45586 0.0164058 4.99717 0.375125 4.79006L1.99892 3.85256C2.35764 3.64545 2.81633 3.76836 3.02344 4.12708L3.96094 5.75088C4.16805 6.1096 4.04514 6.56829 3.68642 6.77539C3.3277 6.9825 2.86901 6.8596 2.6619 6.50088L2.66152 6.50022C2.90238 8.12792 4.30533 9.37695 6 9.37695C6.85293 9.37695 7.63196 9.06056 8.22613 8.53874L9.28834 9.60095C8.42141 10.3935 7.26716 10.877 6 10.877C3.3366 10.877 1.17206 8.74108 1.12576 6.08873Z" fill="#B0B6BF" />
                        </svg>
                        <div>1 ${switchers.payment_currency} = ${switchers.client_rate} ${switchers.target_currency}</div>
                    </div>
                    <div class="awx-amount">${switchers.target_amount} ${switchers.target_currency}</div>
                </td>    
            `);
            $('.awx-you-pay').show();
            $(".totals.charge").hide();
        },

        hideYouPay() {
            // if (!$(".totals.charge").length) {
            //     $(".table-totals tbody").append(`
            //         <tr class="totals charge awx">
            //             <th class="mark" data-bind="i18n: basicCurrencyMessage" scope="row">You will be charged for</th>
            //             <td class="amount">
            //                 <span class="price" data-bind="text: getBaseValue(), attr: {'data-th': basicCurrencyMessage}" data-th="You will be charged for">${parseFloat(window.checkoutConfig..base_grand_total).toFixed(2)} ${window.checkoutConfig.quoteData.base_currency_code}</span>
            //             </td>
            //         </tr>
            //     `);
            // }
            $('.awx-you-pay').hide();
            $(".totals.charge").show();
        },

        showBillingAddress() {
            $('.' + this.getCode() + ' button.editing').hide();
            $('.' + this.getCode() + ' .payment-method-billing-address').show();
            $(this.iframeSelector).hide();
            $(this.qrcodeSelector).hide();
            $("#" + this.getCode() + '-button span').text($t('Confirm'));
        },

        isKlarnaChecked() {
            if (!quote.paymentMethod() || !quote.paymentMethod().method) return false;
            return quote.paymentMethod().method === 'airwallex_payments_klarna';
        },

        placeOrder: async function (data, event) {
            this.validationError('');

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

                    const payload = {
                        cartId: quote.getQuoteId(),
                        paymentMethod: {
                            method: this.code,
                            additional_data: {},
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
                    if (this.isKlarnaChecked()) {
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
