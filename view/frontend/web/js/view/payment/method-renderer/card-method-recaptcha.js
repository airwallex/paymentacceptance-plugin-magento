/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit,
 Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information,
 please view the LICENSE
 * file that was distributed with this source code.
 */
define(
    [
        'Magento_ReCaptchaFrontendUi/js/reCaptcha',
        'jquery',
        'ko',
        'underscore',
        'Airwallex_Payments/js/webapiReCaptchaRegistry',
        'Magento_ReCaptchaFrontendUi/js/reCaptchaScriptLoader',
        'Magento_ReCaptchaFrontendUi/js/nonInlineReCaptchaRenderer'
    ],
    function (Component, $, ko, _, registry, reCaptchaLoader, nonInlineReCaptchaRenderer) {
        'use strict';

        return Component.extend({
            defaults: {
                reCaptchaId: 'airwallex-payments-card-recaptcha',
                autoTrigger: false
            },

            /**
             * recaptchaId: bool map
             */
            _isInvisibleType: {},

            parentFormId: 'airwallex-payments-card-form',

            /**
             * Initialize reCAPTCHA after first rendering
             */
            initCaptcha: function () {
                let $parentForm,
                    $reCaptcha,
                    widgetId,
                    parameters;

                if (typeof this.settings === 'undefined'
                    && window.checkoutConfig?.payment?.airwallex_payments?.recaptcha_settings) {
                    this.settings = window.checkoutConfig.payment.airwallex_payments.recaptcha_settings;
                }

                if (this.captchaInitialized || this.settings === void 0) {
                    return;
                }

                this.captchaInitialized = true;

                $parentForm = $('#' + this.parentFormId);
                $reCaptcha = $('#' + this.getReCaptchaId());

                if (this.settings === undefined) {
                    return;
                }

                parameters = _.extend(
                    {
                        'callback': function (token) { // jscs:ignore jsDoc
                            this.reCaptchaCallback(token);
                            this.validateReCaptcha(true);
                        }.bind(this),
                        'expired-callback': function () {
                            this.validateReCaptcha(false);
                        }.bind(this)
                    },
                    this.settings.rendering
                );

                if (parameters.size === 'invisible' && parameters.badge !== 'inline') {
                    nonInlineReCaptchaRenderer.add($reCaptcha, parameters);
                }

                // eslint-disable-next-line no-undef
                widgetId = grecaptcha.render(this.getReCaptchaId(), parameters);
                this.initParentForm($parentForm, widgetId);
            },

            /**
             * Checking that reCAPTCHA is invisible type
             * @returns {Boolean}
             */
            getIsInvisibleRecaptcha: function () {
                if (this.settings ===

                    void 0) {
                    return false;
                }

                return this.settings.invisible;
            },

            /**
             * Register this ReCaptcha.
             *
             * @param {Object} parentForm
             * @param {String} widgetId
             */
            initParentForm: function (parentForm, widgetId) {
                let self = this,
                    trigger;

                registry._widgets[this.getReCaptchaId()] = widgetId;

                trigger = function () {
                    self.reCaptchaCallback(grecaptcha.getResponse(widgetId));
                };
                registry._isInvisibleType[this.getReCaptchaId()] = false;

                if (this.getIsInvisibleRecaptcha()) {
                    trigger = function () {
                        const response = grecaptcha.execute(widgetId);
                        if (typeof response === 'object' && typeof response.then === 'function') {
                            response.then(function (token) {
                                self.reCaptchaCallback(token);
                            });
                        } else {
                            self.reCaptchaCallback(response);
                        }
                    };
                    registry._isInvisibleType[this.getReCaptchaId()] = true;
                }

                if (this.autoTrigger) {
                    //Validate ReCaptcha when initiated
                    trigger();
                    registry.triggers[this.getReCaptchaId()] = new Function();
                } else {
                    registry.triggers[this.getReCaptchaId()] = trigger;
                }
            },

            /**
             * Provide the token to the registry.
             *
             * @param {String} token
             */
            reCaptchaCallback: function (token) {
                //Make the token retrievable in other UI components.
                registry.tokens[this.getReCaptchaId()] = token;

                if (token !== null && typeof registry._listeners[this.getReCaptchaId()] !== 'undefined') {
                    registry._listeners[this.getReCaptchaId()](token);
                }
            },

            getRegistry: function () {
                return registry;
            },

            reset: function () {
                delete registry.tokens[this.getReCaptchaId()];
                if (typeof registry._widgets[this.getReCaptchaId()] !== 'undefined'
                    && registry._widgets[this.getReCaptchaId()] !== null) {
                    grecaptcha.reset(registry._widgets[this.getReCaptchaId()]);
                }
            }
        });
    }
);

