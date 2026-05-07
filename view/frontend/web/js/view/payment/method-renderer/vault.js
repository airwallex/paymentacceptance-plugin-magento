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
/*browser:true*/
define([
    'ko',
    'jquery',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Checkout/js/model/quote',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler'
], function (
    ko,
    $,
    VaultComponent,
    quote,
    utils,
    addressHandler
) {
    'use strict';

    return VaultComponent.extend({
        validationError: ko.observable(),
        paymentMethodId: ko.observable(),
        autoCapture: !!window.checkoutConfig.payment.airwallex_payments.cc_auto_capture,
        cvcElement: undefined,
        cvcDetail: undefined,
        id: '',
        defaults: {
            active: false,
            template: 'Airwallex_Payments/payment/vault',
        },

        /**
         * @returns {exports}
         */
        initObservable: function () {
            this._super().observe(['active']);
            return this;
        },

        /**
         * Is payment option active?
         *
         * @returns {boolean}
         */
        isActive: function () {
            let active = this.getId() === this.isChecked();

            this.active(active);
            return active;
        },

        /**
         * Return the payment method code.
         *
         * @returns {string}
         */
        getCode: function () {
            return 'airwallex_cc_vault';
        },

        /**
         * Get last 4 digits of card
         *
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         *
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         *
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        /**
         * Get card icons
         *
         * @returns {String}
         */
        getIcons: function (type) {
            if (type.toLowerCase() === 'union pay') {
                let vi = window.checkoutConfig.payment.ccform.icons['VI'];
                let ret = JSON.parse(JSON.stringify(vi));
                ret.url = ret.url.replace('vi.png', 'un.png');
                return ret;
            }
            for (const [name, obj] of Object.entries(window.checkoutConfig.payment.ccform.icons)) {
                if ('amex' === type.toLowerCase()) {
                    type = 'AE';
                    break
                }
                if (obj.title.toLowerCase() === type.toLowerCase()) {
                    type = name;
                    break;
                }
            }
            return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(type) ?
                window.checkoutConfig.payment.ccform.icons[type]
                : false;
        },

        getBillingInformation: function () {
            const billingAddress = quote.billingAddress();
            billingAddress.email = quote.guestEmail;
            addressHandler.setIntentConfirmBillingAddressFromOfficial(billingAddress);
            return addressHandler.intentConfirmBillingAddressFromOfficial;
        },

        isAirwallexCustomerIdSame() {
            return this.details.customer_id === window.checkoutConfig.payment.airwallex_payments.airwallex_customer_id;
        },

        initCvcForm: async function (id, type) {
            this.id = id;
            $('body').trigger('processStart');
            if (this.cvcElement) this.cvcElement.destroy();
            Airwallex.init({
                env: window.checkoutConfig.payment.airwallex_payments.mode,
                origin: window.location.origin,
            });
            if (this.cvcDetail) this.cvcDetail.complete = false;
            this.validationError('');
            this.cvcElement = Airwallex.createElement('cvc', {
                placeholder: 'CVC',
                cvcLength: (['amex', 'american express'].indexOf(type.toLowerCase()) !== -1) ? 4 : 3
            });
            const domElement = this.cvcElement.mount(id + '-cvc', { autoCapture: this.autoCapture });
            domElement.addEventListener('onReady', (event) => {
                $('body').trigger('processStop');
              });
            this.cvcElement.on('change', (event) => {
                this.cvcDetail = event.detail;
                if (this.cvcDetail.complete) {
                    this.validationError('');
                }
            })
        },

        async placeOrder(data, event) {
            const self = this;
            this.validationError('');

            if (event) {
                event.preventDefault();
            }

            if (!this.cvcDetail || !this.cvcDetail.complete) {
                this.validationError($.mage.__('Card Verification Code is incomplete.'));
                return
            }

            if (!utils.validateAgreements('.payment-method._active .checkout-agreements input[type="checkbox"]')) {
                return;
            }

            await utils.pay(self, 'vault', quote);
        }
    });
});
