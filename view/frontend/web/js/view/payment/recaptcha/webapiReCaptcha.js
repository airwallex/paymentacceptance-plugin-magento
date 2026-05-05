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
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

// jscs:disable jsDoc

/* global grecaptcha */
define(
    [
        'Magento_ReCaptchaFrontendUi/js/reCaptcha',
        'Airwallex_Payments/js/view/payment/recaptcha/webapiReCaptchaRegistry',
    ],
    function (Component, registry) {
        'use strict';

        return Component.extend({
            defaults: {
                autoTrigger: false
            },

            /**
             * Provide the token to the registry.
             *
             * @param {String} token
             */
            reCaptchaCallback: function (token) {
                //Make the token retrievable in other UI components.
                registry.tokens[this.getReCaptchaId()] = token;

                if (typeof registry._listeners[this.getReCaptchaId()] !== 'undefined') {
                    registry._listeners[this.getReCaptchaId()](token);
                }
            },

            /**
             * Register this ReCaptcha.
             *
             * @param {Object} parentForm
             * @param {String} widgetId
             */
            initParentForm: function (parentForm, widgetId) {
                var self = this,
                    trigger;

                if (this.getIsInvisibleRecaptcha()) {
                    trigger = function () {
                        grecaptcha.execute(widgetId);
                    };
                } else {
                    trigger = function () {
                        self.reCaptchaCallback(grecaptcha.getResponse(widgetId));
                    };
                }

                if (this.autoTrigger) {
                    //Validate ReCaptcha when initiated
                    trigger();
                    registry.triggers[this.getReCaptchaId()] = new Function();
                } else {
                    registry.triggers[this.getReCaptchaId()] = trigger;
                }
                this.tokenField = null;
            }
        });
    }
);
