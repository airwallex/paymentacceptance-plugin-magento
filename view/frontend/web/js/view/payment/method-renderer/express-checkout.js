define(
    [
        'jquery',
        'ko',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'uiComponent',
        'Magento_Ui/js/modal/modal',
        'Airwallex_Payments/js/view/payment/method-renderer/card-method-recaptcha', //
        'Magento_Customer/js/model/authentication-popup',
    ],
    function (
        $,
        ko,
        storage,
        customerData,
        urlBuilder,
        Component,
        modal,
        cardMethodRecaptcha,
        popup,
    ) {
        'use strict';
        return Component.extend({
            code: 'airwallex_payments_express',
            defaults: {
                paymentConfig: {},
                recaptcha: null,
                googlepay: null,
                quoteRemote: {},
                guestEmail: "",
                billingAddress: {},
                shippingMethods: [],
                methods: [],
                productFormSelector: "#product_addtocart_form",
                buttonMaskSelector: '.aws-button-mask',
                isProductAdded: false,
                cartPageIdentitySelector: '.cart-summary',
                checkoutPageIdentitySelector: '#co-payment-form',
                minicartExpressSelector: '#minicart-content-wrapper .airwallex-express-checkout',
                expressSelector: '.airwallex-express-checkout',
                showMinicartSelector: '.showcart'
            },

            getOptions() {
                return {
                    mode: 'payment',
                    buttonColor: this.paymentConfig.express_style.google_pay_button_theme,
                    buttonType: this.paymentConfig.express_style.google_pay_button_type,
                    emailRequired: true,
                    billingAddressRequired: true,
                    billingAddressParameters: {
                        format: 'FULL',
                        phoneNumberRequired: this.paymentConfig.is_express_phone_required
                    },
                    merchantInfo: {
                        merchantName: this.paymentConfig.express_seller_name || '',
                    },
                    autoCapture: this.paymentConfig.is_express_capture_enabled,
                }
            },

            isRequireShippingOption() {
                if (this.isProductPage()) {
                    if (!parseInt(this.quoteRemote.items_qty)) {
                        return this.quoteRemote.product_type !== 'virtual'
                    }
                    return !this.quoteRemote.is_virtual || this.quoteRemote.product_type !== 'virtual'
                }
                return this.isRequireShippingAddress()
            },

            isRequireShippingAddress() {
                if (this.isProductPage()) {
                    return true
                }
                if (this.isCheckoutPage()) {
                    return false;
                }
                return !this.quoteRemote.is_virtual
            },

            getGooglePayRequestOptions: function () {
                let paymentDataRequest = this.getOptions()
                paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
                if (this.isRequireShippingAddress()) {
                    paymentDataRequest.callbackIntents.push('SHIPPING_ADDRESS');
                    paymentDataRequest.shippingAddressRequired = true;
                    paymentDataRequest.shippingAddressParameters = {
                        phoneNumberRequired: this.paymentConfig.is_express_phone_required,
                    };
                }

                if (this.isRequireShippingOption()) {
                    paymentDataRequest.callbackIntents.push('SHIPPING_OPTION');
                    paymentDataRequest.shippingOptionRequired = true;
                }

                const transactionInfo = {
                    amount: {
                        value: this.formatCurrency(this.quoteRemote.grand_total),
                        currency: $('[property="product:price:currency"]').attr("content") || this.getCurrencyCode(),
                    },
                    countryCode: this.getCountryCode(),
                    displayItems: this.getDisplayItems(),
                };

                return Object.assign(paymentDataRequest, transactionInfo);
            },

            getExpressButtonSort() {
                return this.paymentConfig.express_button_sort
            },

            getCountryCode() {
                return this.paymentConfig.country_code
            },

            getDisplayItems() {
                let res = [];
                for (let key in this.quoteRemote) {
                    if (this.quoteRemote[key] === '0.0000' || !this.quoteRemote[key]) {
                        continue
                    }
                    if (key === 'shipping_amount') {
                        res.push({
                            'label': 'Shipping',
                            'type': 'LINE_ITEM',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'tax_amount') {
                        res.push({
                            'label': 'Tax',
                            'type': 'TAX',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'subtotal') {
                        res.push({
                            'label': 'Subtotal',
                            'type': 'SUBTOTAL',
                            'price': this.formatCurrency(this.quoteRemote[key])
                        })
                    } else if (key === 'subtotal_with_discount') {
                        if (this.quoteRemote[key] !== this.quoteRemote['subtotal']) {
                            res.push({
                                'label': 'Discount',
                                'type': 'LINE_ITEM',
                                'price': '-' + this.getDiscount().toString()
                            })
                        }
                    }
                }
                return res
            },

            getDiscount() {
                let diff = this.quoteRemote['subtotal'] - this.quoteRemote['subtotal_with_discount']
                return diff.toFixed(2)
            },

            formatCurrency(v) {
                return parseFloat(v).toFixed(2)
            },

            getCurrencyCode() {
                return this.quoteRemote.quote_currency_code
            },

            setGuestEmail(email) {
                this.guestEmail = email
            },

            formatShippingMethodsToGoogle(methods, selectedMethod) {
                const shippingOptions = methods.map(addr => {
                    return {
                        id: addr.carrier_code,
                        label: addr.method_code,
                        description: addr.carrier_title,
                    };
                });

                return {
                    shippingOptions,
                    defaultSelectedOptionId: selectedMethod.carrier_code
                };
            },

            isVirtualInCheckout() {
                return window.checkoutConfig && window.checkoutConfig.quoteData && window.checkoutConfig.quoteData.is_virtual
            },

            async fetchQuote() {
                let subUrl = 'rest/V1/airwallex/payments/get-quote';
                if (this.isProductPage()) {
                    subUrl += "?is_product_page=1&product_id=" + $("input[name=product]").val()
                }
                let apiUrl = urlBuilder.build(subUrl)
                const resp = await storage.get(
                    apiUrl, true, 'application/json', {}
                );
                let obj = JSON.parse(resp)
                this.quoteRemote = obj
                this.paymentConfig = obj.settings
            },

            postShippingInformation(payload) {
                let url = 'rest/V1/carts/mine/shipping-information';
                if (!this.isLoggedIn()) {
                    url = 'rest/V1/guest-carts/' + this.getCartId() + '/shipping-information'
                }
                return storage.post(
                    urlBuilder.build(url), JSON.stringify(payload)
                );
            },

            postBillingAddress(payload) {
                let url = 'rest/V1/carts/mine/billing-address';
                if (!this.isLoggedIn()) {
                    url = 'rest/V1/guest-carts/' + this.getCartId() + '/billing-address'
                }
                return storage.post(
                    urlBuilder.build(url), JSON.stringify(payload)
                );
            },

            estimateShippingMethods(address) {
                let url = 'rest/V1/carts/mine/estimate-shipping-methods';
                if (!this.isLoggedIn()) {
                    url = 'rest/V1/guest-carts/' + this.getCartId() + '/estimate-shipping-methods'
                }
                return storage.post(
                    urlBuilder.build(url), JSON.stringify({address})
                );
            },

            initModal() {
                modal({
                    type: 'popup',
                    responsive: true,
                    title: 'Error',
                    buttons: [{
                        text: $.mage.__('OK'),
                        class: '',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                }, $('#awx-modal'));
            },

            initProductPageFormClickEvents() {
                if (this.isProductPage()) {
                    $(this.expressSelector).on("mouseover", () => {
                        this.validateProductOptions();
                    })
                    $(this.productFormSelector).on('click', () => {
                        this.validateProductOptions();
                    })
                    $(this.buttonMaskSelector).on('click', (e) => {
                        e.stopPropagation()
                        $(this.productFormSelector).valid()
                    })
                }
            },

            initMinicartClickEvents() {
                if (!$(this.showMinicartSelector).length) {
                    return
                }

                let recreateGooglepay = async () => {
                    Airwallex.destroyElement('googlePayButton');
                    await this.fetchQuote();

                    let options = this.getGooglePayRequestOptions();
                    this.googlepay = Airwallex.createElement('googlePayButton', options)
                    this.googlepay.mount('awx-google-pay');
                    this.attachEventsToGooglepay()
                }
                $(this.showMinicartSelector).on("click", recreateGooglepay)
                let cartData = customerData.get('cart')
                cartData.subscribe(recreateGooglepay, this);
            },

            showMessage(errorMessage) {
                $("#awx-modal .modal-body-content").html(errorMessage)
                $('#awx-modal').modal('openModal');
            },

            isProductPage() {
                return !!$(this.productFormSelector).length
            },

            isCartPage() {
                return !!$(this.cartPageIdentitySelector).length
            },

            isCheckoutPage() {
                return !!$(this.checkoutPageIdentitySelector).length
            },

            validateProductOptions() {
                let formSelector = $(this.productFormSelector);
                if (formSelector.length === 0 || !formSelector.validate) {
                    return
                }
                if ($(formSelector).validate().checkForm()) {
                    $(this.buttonMaskSelector).hide()
                } else {
                    $(this.buttonMaskSelector).show()
                }
            },

            addToCartOptions() {
                let formData = new FormData();
                let serializedArray = $(this.productFormSelector).serializeArray();
                $.each(serializedArray, function (index, field) {
                    formData.append(field.name, field.value);
                });

                return {
                    url: urlBuilder.build('rest/V1/airwallex/payments/add-to-cart'),
                    data: formData,
                    processData: false,
                    contentType: false,
                    type: 'POST',
                };
            },

            async loadPayment(from) {
                if (this.isCartPage() || this.isProductPage()) {
                    $(this.minicartExpressSelector).remove()
                    // there can only one express
                    if (window !== from) {
                        return
                    }
                }

                this.initMinicartClickEvents()

                this.initModal()
                this.initProductPageFormClickEvents()

                await this.fetchQuote();
                if (!this.paymentConfig.is_express_active) {
                    $(this.expressSelector).hide()
                    return
                }

                this.recaptcha = cardMethodRecaptcha();
                // this.recaptcha.renderReCaptcha();

                Airwallex.init({
                    env: this.paymentConfig.mode,
                    origin: window.location.origin,
                });

                this.createGooglepay()

                window.addEventListener('hashchange', async () => {
                    if (window.location.hash === '#payment') {
                        Airwallex.destroyElement('googlePayButton');
                        // we need update quote, because we choose shipping method last step
                        await this.fetchQuote();
                        this.createGooglepay()
                    }
                });
            },

            createGooglepay() {
                this.googlepay = Airwallex.createElement('googlePayButton', this.getGooglePayRequestOptions())
                this.googlepay.mount('awx-google-pay');
                this.attachEventsToGooglepay()
            },

            attachEventsToGooglepay() {
                let self = this;
                let updateQuoteByShipment = async (event) => {
                    if (self.isProductPage()) {
                        self.isProductAdded = true
                        let res = await $.ajax(self.addToCartOptions())
                        Object.assign(self.quoteRemote, JSON.parse(res))
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart'], true);
                    }
                    // 1. estimateShippingMethods
                    if (this.isRequireShippingAddress()) {
                        let addr = self.getIntermediateShippingAddressFromGoogle(event.detail.intermediatePaymentData.shippingAddress)
                        this.methods = await this.estimateShippingMethods(addr)
                    }

                    // 2. postShippingInformation
                    let {information, selectedMethod} = self.constructAddressInformationFromGoogle(
                        event.detail.intermediatePaymentData, this.methods
                    )
                    await self.postShippingInformation(information)

                    // 3. update quote
                    await self.fetchQuote()

                    let options = this.getGooglePayRequestOptions();
                    if (this.isRequireShippingOption()) {
                        options.shippingOptionParameters = this.formatShippingMethodsToGoogle(this.methods, selectedMethod)
                    }
                    this.googlepay.update(options);
                }

                this.googlepay.on('shippingAddressChange', updateQuoteByShipment);

                this.googlepay.on('shippingMethodChange', updateQuoteByShipment);

                this.googlepay.on('authorized', async (event) => {
                    self.setGuestEmail(event.detail.paymentData.email)

                    if (self.isRequireShippingAddress()) {
                        // this time google provide full shipping address, we should post to magento
                        let {information} = self.constructAddressInformationFromGoogle(
                            event.detail.paymentData, this.methods
                        )
                        await self.postShippingInformation(information)
                    } else {
                        await self.postBillingAddress({
                            'cartId': this.getCartId(),
                            'address': this.getBillingAddressFromGoogle(event.detail.paymentData.paymentMethodData.info.billingAddress)
                        })
                    }
                    self.setIntentConfirmBillingAddressFromGoogle(event.detail.paymentData);
                    this.placeOrder()
                });

            },

            getData() {
                return {
                    method: this.code,
                    po_number: null,
                    additional_data: {
                        amount: 0,
                        intent_status: 0
                    }
                }
            },

            processPlaceOrderError: function (response) {
                $('body').trigger('processStop');
                let errorMessage = response.message
                if (response.responseText) {
                    if (response.responseText.indexOf('shipping address') !== -1) {
                        errorMessage = $.mage.__('Placing an order is failed: please try another address.')
                    } else {
                        errorMessage = $.mage.__(response.responseJSON.message)
                    }
                }
                $("#awx-modal .modal-body-content").html(errorMessage)
                $('#awx-modal').modal('openModal');
            },

            getCartId() {
                return this.isLoggedIn() ? this.quoteRemote.cart_id : this.quoteRemote.mask_cart_id
            },

            isLoggedIn() {
                return !!this.quoteRemote.customer_id;
            },

            placeOrder: function (data, event) {
                const self = this;

                if (event) {
                    event.preventDefault();
                }

                $('body').trigger('processStart');

                const payload = {
                    cartId: this.getCartId(),
                    paymentMethod: this.getData()
                };

                let serviceUrl;
                if (this.isLoggedIn()) {
                    serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/place-order');
                } else {
                    serviceUrl = urlBuilder.build('rest/V1/airwallex/payments/guest-place-order');
                }

                payload.intent_id = null;

                (new Promise(async function (resolve, reject) {
                    try {
                        payload.xReCaptchaValue = await (new Promise(function (resolve) {
                            self.getRecaptchaToken(resolve);
                        }));

                        if (!self.isLoggedIn()) {
                            payload.email = self.isCheckoutPage() ? $("#customer-email").val() : self.guestEmail;
                        }

                        const intentResponse = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json'
                        );

                        const params = {};
                        params.id = intentResponse.intent_id;
                        params.client_secret = intentResponse.client_secret;
                        params.payment_method = {};
                        params.payment_method.billing = self.billingAddress;

                        payload.intent_id = intentResponse.intent_id;
                        payload.xReCaptchaValue = null;
                        if (self.isRequireShippingOption()) {
                            payload.billingAddress = self.getBillingAddressToPlaceOrder()
                        }
                        await self.googlepay.confirmIntent(params);

                        const endResult = await storage.post(
                            serviceUrl, JSON.stringify(payload), true, 'application/json', {}
                        );
                        resolve(endResult);
                    } catch (e) {
                        reject(e);
                    }
                })).then(function (response) {
                    const clearData = {
                        'selectedShippingAddress': null,
                        'shippingAddressFromData': null,
                        'newCustomerShippingAddress': null,
                        'selectedShippingRate': null,
                        'selectedPaymentMethod': null,
                        'selectedBillingAddress': null,
                        'billingAddressFromData': null,
                        'newCustomerBillingAddress': null
                    };

                    if (response?.responseType !== 'error') {
                        customerData.set('checkout-data', clearData);
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart'], true);
                    }

                    window.location.replace(urlBuilder.build('checkout/onepage/success/'));
                }).catch(
                    self.processPlaceOrderError.bind(self)
                ).finally(
                    function () {
                        self.recaptcha.reset();
                        setTimeout(() => {
                            $('body').trigger('processStop')
                        }, 3000)
                    }
                );
            },

            getRecaptchaToken: function (callback) {
                if (!this.isRecaptchaEnabled) {
                    return callback();
                }

                const reCaptchaId = this.recaptcha.getReCaptchaId(),
                    registry = this.recaptcha.getRegistry();

                if (registry.tokens.hasOwnProperty(reCaptchaId)) {
                    const response = registry.tokens[reCaptchaId];
                    if (typeof response === 'object' && typeof response.then === 'function') {
                        response.then(function (token) {
                            callback(token);
                        });
                    } else {
                        callback(response);
                    }
                } else {
                    registry._listeners[reCaptchaId] = callback;
                    registry.triggers[reCaptchaId]();
                }
            },

            getIntermediateShippingAddressFromGoogle(addr) {
                return {
                    "region": addr.administrativeArea,
                    "country_id": addr.countryCode,
                    "postcode": addr.postalCode,
                    "city": addr.locality,
                }
            },

            getBillingAddressFromGoogle(addr) {
                let names = addr.name.split(' ')
                return {
                    countryId: addr.countryCode,
                    region: addr.administrativeArea,
                    street: [addr.address1 + addr.address2 + addr.address3],
                    telephone: addr.phoneNumber,
                    postcode: addr.postalCode,
                    city: addr.locality,
                    firstname: names[0],
                    lastname: names.length > 1 ? names[names.length - 1] : names[0],
                }
            },

            constructAddressInformationFromGoogle(data, methods) {
                let billingAddress = {}
                if (data.paymentMethodData) {
                    let addr = data.paymentMethodData.info.billingAddress
                    billingAddress = this.getBillingAddressFromGoogle(addr)
                }

                let selectedMethod = methods.find(item => item.carrier_code === data.shippingOptionData.id) || methods[0];
                if (!selectedMethod) {
                    selectedMethod = {}
                }

                let firstname = '', lastname = ''
                if (data.shippingAddress && data.shippingAddress.name) {
                    let names = data.shippingAddress.name.split(' ') || [];
                    firstname = data.shippingAddress.name ? names[0] : '';
                    lastname = names.length > 1 ? names[names.length - 1] : firstname;
                }

                let information = {
                    "addressInformation": {
                        "shipping_address": {},
                        "billing_address": billingAddress,
                        "shipping_method_code": selectedMethod.method_code,
                        "shipping_carrier_code": selectedMethod.carrier_code,
                        "extension_attributes": {}
                    }
                }
                if (this.isRequireShippingAddress()) {
                    information.addressInformation.shipping_address = {
                        "countryId": data.shippingAddress.countryCode,
                        "region": data.shippingAddress.administrativeArea,
                        "street": [data.shippingAddress.address1 + data.shippingAddress.address2 + data.shippingAddress.address3],
                        "telephone": data.shippingAddress.phoneNumber,
                        "postcode": data.shippingAddress.postalCode,
                        "city": data.shippingAddress.locality,
                        firstname,
                        lastname,
                    }
                }
                return {information, selectedMethod}
            },

            getBillingAddressToPlaceOrder() {
                return {
                    "countryId": this.billingAddress.address.country_code,
                    "regionCode": this.billingAddress.address.state,
                    "street": [
                        this.billingAddress.address.street[0],
                    ],
                    "telephone": this.billingAddress.telephone,
                    "postcode": this.billingAddress.address.postcode,
                    "city": this.billingAddress.address.city,
                    "firstname": this.billingAddress.first_name,
                    "lastname": this.billingAddress.last_name,
                }
            },

            setIntentConfirmBillingAddressFromGoogle(data) {
                let addr = data.paymentMethodData.info.billingAddress
                let names = addr.name.split(' ')
                this.billingAddress = {
                    address: {
                        city: addr.locality,
                        country_code: addr.countryCode,
                        postcode: addr.postalCode,
                        state: addr.administrativeArea,
                        street: [addr.address1 + addr.address2 + addr.address3],
                    },
                    first_name: names[0],
                    last_name: names.length > 1 ? names[names.length - 1] : names[0],
                    email: data.email,
                    telephone: addr.phoneNumber
                }
            },
        });
    }
);
