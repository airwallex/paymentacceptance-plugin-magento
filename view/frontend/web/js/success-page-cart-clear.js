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
/**
 * The Magento customer-data section storage singleton
 * (`Magento_Customer/js/customer-data`). Only the members this module touches
 * are typed here.
 */
/** Config injected by the success-page `.phtml` for this storefront hook. */
define([
    'Magento_Customer/js/customer-data'
], function (customerData                     ) {
    'use strict';
    return function (config                                    ) {
        if (!config || !config.isAirwallexPayment) {
            return;
        }
        customerData.set('cart', {});
        customerData.invalidate(['cart']);
        customerData.set('checkout-data', {
            'selectedShippingAddress': null,
            'shippingAddressFromData': null,
            'newCustomerShippingAddress': null,
            'selectedShippingRate': null,
            'selectedPaymentMethod': null,
            'selectedBillingAddress': null,
            'billingAddressFromData': null,
            'newCustomerBillingAddress': null
        });
        customerData.reload(['cart'], true);
    };
});
