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
define([], function () {
    'use strict';

    return {
        /**
         * recaptchaId: token map.
         *
         * Tokens for already verified recaptcha.
         */
        tokens: {},

        /**
         * recaptchaId: triggerFn map.
         *
         * Call a trigger to initiate a recaptcha verification.
         */
        triggers: {},

        /**
         * recaptchaId: callback map
         */
        _listeners: {},

        /**
         * recaptchaId: bool map
         */
        _isInvisibleType: {},

        /**
         * Add a listener to when the ReCaptcha finishes verification
         * @param {String} id - ReCaptchaId
         * @param {Function} func - Will be called back with the token
         */
        addListener: function (id, func) {
            if (this.tokens.hasOwnProperty(id)) {
                func(this.tokens[id]);
            } else {
                this._listeners[id] = func;
            }
        },

        /**
         * Remove a listener
         *
         * @param id
         */
        removeListener: function (id) {
            this._listeners[id] = undefined;
        }
    };
});
