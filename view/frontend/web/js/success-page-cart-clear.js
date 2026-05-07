define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    return function (config) {
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
