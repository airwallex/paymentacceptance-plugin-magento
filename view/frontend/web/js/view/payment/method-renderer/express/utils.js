define([
    'mage/url',
    'jquery',
    'Magento_Ui/js/modal/modal',
], function (
    urlBuilder,
    $,
    modal,
) {
    'use strict';

    return {
        productFormSelector: "#product_addtocart_form",
        cartPageIdentitySelector: '.cart-summary',
        checkoutPageIdentitySelector: '#co-payment-form',
        buttonMaskSelector: '.aws-button-mask',
        expressSelector: '.airwallex-express-checkout',
        expressData: {},
        paymentConfig: {},

        getDiscount(subtotal, subtotal_with_discount) {
            let diff = subtotal - subtotal_with_discount
            return diff.toFixed(2)
        },

        formatCurrency(v) {
            return parseFloat(v).toFixed(2)
        },

        isCartEmpty() {
            return !parseInt(this.expressData.items_qty)
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

        initProductPageFormClickEvents() {
            if (this.isProductPage() && this.isSetActiveInProductPage()) {
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

        isSetActiveInProductPage() {
            return this.paymentConfig.display_area.indexOf('product_page') !== -1
        },

        isSetActiveInCartPage() {
            return this.paymentConfig.display_area.indexOf('cart_page') !== -1
        },

        isFromMinicartAndShouldNotShow(from) {
            if (from !== 'minicart') {
                return false
            }
            if (this.isProductPage() && this.isSetActiveInProductPage()) {
                return true
            }
            return !!(this.isCartPage() && this.isSetActiveInCartPage());
        },

        isRequireShippingOption() {
            if (this.isProductPage()) {
                if (this.isCartEmpty()) {
                    return this.expressData.product_type !== 'virtual'
                }
                return !this.expressData.is_virtual || this.expressData.product_type !== 'virtual'
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
            return !this.expressData.is_virtual
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

        getCartId() {
            return this.isLoggedIn() ? this.expressData.cart_id : this.expressData.mask_cart_id
        },

        isLoggedIn() {
            return !!this.expressData.customer_id;
        },

        error(response) {
            let modalSelector = $('#awx-modal')
            modal({title: 'Error'}, modalSelector);

            $('body').trigger('processStop');
            let errorMessage = response.message
            if (response.responseText) {
                if (response.responseText.indexOf('shipping address') !== -1) {
                    errorMessage = $.mage.__('Placing an order is failed: please try another address.')
                } else if (response.responseJSON) {
                    errorMessage = $.mage.__(response.responseJSON.message)
                }
            }

            $("#awx-modal .modal-body-content").html(errorMessage)
            modalSelector.modal('openModal');
        },
    }
});
