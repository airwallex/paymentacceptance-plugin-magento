define([
    'mage/url',
    'mage/storage',
], function (
    urlBuilder,
    storage,
) {
    'use strict';

    return {
        selectedMethod: {},
        regionId: "",
        intentConfirmBillingAddressFromGoogle: {},
        intentConfirmBillingAddressFromOfficial: {},

        postBillingAddress(payload, isLoggedIn, cartId) {
            let url = 'rest/V1/carts/mine/billing-address';
            if (!isLoggedIn) {
                url = 'rest/V1/guest-carts/' + cartId + '/billing-address';
            }
            return storage.post(
                urlBuilder.build(url), JSON.stringify(payload), undefined, 'application/json', {}
            );
        },

        postShippingInformation(payload, isLoggedIn, cartId) {
            let url = 'rest/V1/carts/mine/shipping-information';
            if (!isLoggedIn) {
                url = 'rest/V1/guest-carts/' + cartId + '/shipping-information';
            }
            return storage.post(
                urlBuilder.build(url), JSON.stringify(payload), undefined, 'application/json', {}
            );
        },

        getIntermediateShippingAddress(addr) {
            return {
                "region": addr.administrativeArea || '',
                "country_id": addr.countryCode,
                "postcode": addr.postalCode || '',
                "city": addr.locality || '',
            };
        },

        getBillingAddressFromGoogle(addr) {
            let names = addr.name.split(' ');
            return {
                countryId: addr.countryCode,
                region: addr.administrativeArea,
                regionId: 0,
                street: [addr.address1 + ' ' + addr.address2 + ' ' + addr.address3],
                telephone: addr.phoneNumber,
                postcode: addr.postalCode,
                city: addr.locality,
                firstname: names[0],
                lastname: names.length > 1 ? names[names.length - 1] : names[0],
            };
        },

        getBillingAddressFromApple(addr, phone) {
            return {
                countryId: addr.countryCode,
                region: addr.administrativeArea,
                regionId: 0,
                street: addr.addressLines,
                telephone: phone,
                postcode: addr.postalCode,
                city: addr.locality,
                firstname: addr.givenName,
                lastname: addr.familyName,
            };
        },

        constructAddressInformationFromGoogle(data) {
            let names = data.shippingAddress.name.split(' ') || [];
            let firstname = data.shippingAddress.name ? names[0] : '';
            let lastname = names.length > 1 ? names[names.length - 1] : firstname;

            return {
                "addressInformation": {
                    "shipping_address": {
                        "countryId": data.shippingAddress.countryCode,
                        "regionId": this.regionId || 0,
                        "region": data.shippingAddress.administrativeArea,
                        "street": [data.shippingAddress.address1 + ' ' + data.shippingAddress.address2 + ' ' + data.shippingAddress.address3],
                        "telephone": data.shippingAddress.phoneNumber,
                        "postcode": data.shippingAddress.postalCode,
                        "city": data.shippingAddress.locality,
                        firstname,
                        lastname,
                    },
                    "billing_address": this.getBillingAddressFromGoogle(data.paymentMethodData.info.billingAddress),
                    "shipping_method_code": this.selectedMethod ? this.selectedMethod.method_code : "",
                    "shipping_carrier_code": this.selectedMethod ? this.selectedMethod.carrier_code : "",
                    "extension_attributes": {}
                }
            };
        },

        constructAddressInformationFromApple(data) {
            return {
                "addressInformation": {
                    "shipping_address": {
                        "countryId": data.shippingContact.countryCode,
                        "regionId": this.regionId || 0,
                        "region": data.shippingContact.administrativeArea,
                        "street": data.shippingContact.addressLines,
                        "telephone": data.shippingContact.phoneNumber,
                        "postcode": data.shippingContact.postalCode,
                        "city": data.shippingContact.locality,
                        "firstname": data.shippingContact.givenName,
                        "lastname": data.shippingContact.familyName,
                    },
                    "billing_address": this.getBillingAddressFromApple(data.billingContact, data.shippingContact.phoneNumber),
                    "shipping_method_code": this.selectedMethod ? this.selectedMethod.method_code : "",
                    "shipping_carrier_code": this.selectedMethod ? this.selectedMethod.carrier_code : "",
                    "extension_attributes": {}
                }
            };
        },

        setIntentConfirmBillingAddressFromGoogle(data) {
            let addr = data.paymentMethodData.info.billingAddress;
            let names = addr.name.split(' ');
            this.intentConfirmBillingAddressFromGoogle = {
                address: {
                    city: addr.locality,
                    country_code: addr.countryCode,
                    postcode: addr.postalCode,
                    state: addr.administrativeArea,
                    street: [addr.address1 + ' ' + addr.address2 + ' ' + addr.address3],
                },
                first_name: names[0],
                last_name: names.length > 1 ? names[names.length - 1] : names[0],
                email: data.email,
                telephone: addr.phoneNumber
            };
        },

        setIntentConfirmBillingAddressFromApple(addr, email) {
            this.intentConfirmBillingAddressFromGoogle = {
                address: {
                    city: addr.locality,
                    country_code: addr.countryCode,
                    postcode: addr.postalCode,
                    state: addr.administrativeArea,
                    street: addr.addressLines,
                },
                first_name: addr.givenName,
                last_name: addr.familyName,
                email,
                telephone: addr.phoneNumber
            };
        },

        setIntentConfirmBillingAddressFromOfficial(billingAddress) {
            this.intentConfirmBillingAddressFromOfficial = {
                address: {
                    city: billingAddress.city,
                    country_code: billingAddress.country_id,
                    postcode: billingAddress.postcode,
                    state: billingAddress.region,
                    street: Array.isArray(billingAddress.street) ? billingAddress.street.join(', ') : billingAddress.street
                },
                first_name: billingAddress.firstname,
                last_name: billingAddress.lastname,
                email: billingAddress.email
            };
        },

        formatShippingMethodsToGoogle(methods, selectedMethod) {
            const shippingOptions = methods.map(addr => {
                return {
                    id: addr.method_code,
                    label: addr.method_title,
                    description: addr.carrier_title,
                };
            });

            return {
                shippingOptions,
                defaultSelectedOptionId: selectedMethod.method_code
            };
        },

        formatShippingMethodsToApple(methods) {
            return methods.map(addr => {
                return {
                    identifier: addr.method_code,
                    label: addr.method_title,
                    detail: addr.carrier_title,
                    amount: addr.amount
                };
            });
        },
    };
});
