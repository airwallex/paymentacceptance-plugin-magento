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
                                                         
                                                                  
                                                                                         

                       
                             
                              
                          
                        
                                        
                                       
                                                   
                                                                              
                                                  
                                                                               
                                                                             
                                                         
                                                                                          
                                                            
                                              
                                                                   
                                                        
                                                 
                           
                                                                  
                               
 

                                
                                                                  
                                                                  
                                                                  
                                                                                                
                                                                  
                                                              
                                                                  
                                                              
                                                                  
                                                              
                                                                                                     
                                                                                                                             
 

/** Options built by `getOptions` then extended by `getRequestOptions`. */
                                   
                 
                        
                       
                           
                                    
                                                                               
                                           
                         
                                  
                               
                                      
                                                                 
                                     
                       
                                                    
                            
                                                                      
                            
      
                                                                            
                                                       
                           
 

/** `this` receiver for the googlepay module's methods (the returned object). */
                                                 
                  
 

define([
    'jquery',
    'Airwallex_Payments/js/view/payment/utils',
    'Airwallex_Payments/js/view/payment/method-renderer/address/address-handler',
], function (
    $              ,
    utils             ,
    addressHandler                      ,
) {
    'use strict';

    return {
        elements: {},
        expressData: {},
        paymentConfig: {},
        methods: [],
        selectedMethod: {},

        create(                     that                     ) {
            let element = Airwallex.createElement('googlePayButton', this.getRequestOptions(true)                                             );
            this.elements[that.from] = element;
            let el = element.mount('awx-google-pay-' + that.from);
            utils.attachHeightGuard(element, 'googlePayButton');
            el.addEventListener('onReady', (event) => {
                utils.initCheckoutPageExpressCheckoutClick();
                $(".express-title").show();
                utils.showAgreements();
            });
            this.attachEvents(that, element);
            utils.loadRecaptcha(that.isShowRecaptcha);
        },

        confirmIntent(                     from        , params                         ) {
            return this.elements[from].confirmIntent(params);
        },

        destroy(                     from        ) {
            if (this.elements[from]) {
                utils.detachHeightGuard(this.elements[from]);
                this.elements[from].destroy();
                delete this.elements[from];
            }
        },

        attachEvents(                     that                     , element                  ) {
            let updateQuoteByShipment = async (event     ) => {
                await utils.addToCart(that);

                let addr = addressHandler.getIntermediateShippingAddress(event.detail.intermediatePaymentData.shippingAddress, 'google');

                try {
                    let methodId = "";
                    if (event.detail.intermediatePaymentData.shippingOptionData) {
                        methodId = event.detail.intermediatePaymentData.shippingOptionData.id;
                    }
                    await that.postAddress(addr, methodId);
                } catch (e) {
                    utils.error(e);
                }

                let options = this.getRequestOptions();
                if (utils.isRequireShippingOption()) {
                    const currency = $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code || '';
                    const sign = currency ? utils.getCurrencySign(currency) : '';
                    const methods = this.methods.map(m => {
                        if (m.amount == null || m.amount === '' || !currency) {
                            return { ...m };
                        }
                        // Free shipping: show a bare "0" rather than e.g. "$0.00".
                        const amount = Number(m.amount) === 0
                            ? '0'
                            : `${sign}${utils.convertToAwxAmount(m.amount, currency)}`;
                        return { ...m, amount };
                    });
                    options.shippingOptionParameters = addressHandler.formatShippingMethodsToGoogle(methods, this.selectedMethod);
                }
                element.update(options);
            };

            element.on('click', () => {
                if (utils.isProductPage()) {
                    $('#btn-minicart-close').click();
                }
            });

            element.on('shippingAddressChange', updateQuoteByShipment);

            element.on('shippingMethodChange', updateQuoteByShipment);

            element.on('authorized', async (event     ) => {
                let data = event.detail.paymentData;
                that.setGuestEmail(data.email);
                try {
                    if (utils.isRequireShippingAddress()) {
                        // this time google provide full shipping address, we should post to magento
                        let information = addressHandler.constructAddressInformationFromGoogle(data);
                        await addressHandler.postShippingInformation(information, utils.isLoggedIn(), utils.getCartId());
                    } else {
                        await addressHandler.postBillingAddress({
                            'cartId': utils.getCartId(),
                            'address': addressHandler.getBillingAddressFromGoogle(data.paymentMethodData.info.billingAddress)
                        }, utils.isLoggedIn(), utils.getCartId());
                    }
                    addressHandler.setIntentConfirmBillingAddressFromGoogle(data);
                    that.placeOrder('googlepay');
                } catch (e) {
                    utils.error(e);
                }
            });
        },

        getRequestOptions(                     initial          = false)                          {
            let paymentDataRequest = this.getOptions()                           ;
            paymentDataRequest.callbackIntents = ['PAYMENT_AUTHORIZATION'];
            if (utils.isRequireShippingAddress()) {
                paymentDataRequest.callbackIntents.push('SHIPPING_ADDRESS');
                paymentDataRequest.shippingAddressRequired = true;
                paymentDataRequest.shippingAddressParameters = {
                    phoneNumberRequired: this.paymentConfig.is_express_phone_required ,
                };
            }

            if (utils.isRequireShippingOption()) {
                paymentDataRequest.callbackIntents.push('SHIPPING_OPTION');
                paymentDataRequest.shippingOptionRequired = true;
            }

            const showZero = initial && utils.isProductPage();
            const transactionInfo = {
                amount: {
                    value: showZero ? '0.00' : utils.formatCurrency(this.expressData.grand_total ),
                    currency: $('[property="product:price:currency"]').attr("content") || this.expressData.quote_currency_code,
                },
                countryCode: this.paymentConfig.country_code,
                displayItems: showZero ? [] : this.getDisplayItems(),
            };

            return Object.assign(paymentDataRequest, transactionInfo);
        },

        getOptions(                   )                          {
            return {
                mode: 'payment',
                buttonColor: this.paymentConfig.express_style .theme,
                buttonType: this.paymentConfig.express_style .call_to_action,
                emailRequired: true,
                billingAddressRequired: true,
                billingAddressParameters: {
                    format: 'FULL',
                    phoneNumberRequired: this.paymentConfig.is_express_phone_required 
                },
                merchantInfo: {
                    merchantName: this.paymentConfig.express_seller_name || '',
                },
                autoCapture: this.paymentConfig.is_express_auto_capture ,
                allowedCardNetworks: this.getSupportedNetworks(this.paymentConfig.allowed_card_networks .googlepay)
            };
        },

        getSupportedNetworks(supportBrands          )           {
            let brands = supportBrands.map(function (brand) {
                return brand.toUpperCase();
            }).filter(function (brand) {
                return brand !== 'UNIONPAY' && brand !== 'MAESTRO' && brand !== 'DINERS';
            });
            return brands;
        },

        getDisplayItems(                   )                                 {
            let res                                 = [];
            for (let key in this.expressData) {
                if (this.expressData[key] === '0.0000' || !this.expressData[key]) {
                    continue;
                }
                if (key === 'shipping_amount') {
                    res.push({
                        'label': 'Shipping',
                        'type': 'LINE_ITEM',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'tax_amount') {
                    res.push({
                        'label': 'Tax',
                        'type': 'TAX',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal') {
                    res.push({
                        'label': 'Subtotal',
                        'type': 'SUBTOTAL',
                        'price': utils.formatCurrency(this.expressData[key])
                    });
                } else if (key === 'subtotal_with_discount') {
                    if (this.expressData[key] !== this.expressData['subtotal']) {
                        res.push({
                            'label': 'Discount',
                            'type': 'LINE_ITEM',
                            'price': '-' + utils.getDiscount(this.expressData['subtotal'] , this.expressData['subtotal_with_discount'] ).toString()
                        });
                    }
                }
            }
            return res;
        },
    };
});
