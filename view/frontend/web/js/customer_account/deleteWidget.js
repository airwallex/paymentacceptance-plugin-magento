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
define([
    'jquery',
    'Magento_Ui/js/modal/modalToggle',
    'mage/url',
    'mage/translate',
], function ($, modalToggle, url) {
    'use strict';

    return function (config, deleteButton) {
        config.buttons = [
            {
                text: $.mage.__('Cancel'),
                class: 'action secondary cancel'
            }, {
                text: $.mage.__('Delete'),
                class: 'action primary',

                /**
                 * Default action on button click
                 */
                click: function (event) { //eslint-disable-line no-unused-vars
                    // $('body').trigger('processStart');
                    let removeUrl = url.build('rest/V1/airwallex/saved_cards/') + $(deleteButton).data('id');

                    $.ajax({
                        url: removeUrl,
                        method: 'DELETE',
                        success: (function() {
                            $(deleteButton.form).trigger('submit');
                        }).bind(this),
                        error: function(xhr, status, error) {
                            $('body').trigger('processStop');
                            $('.modal-content div').html('');
                        }
                    });
                }
            }
        ];

        modalToggle(config, deleteButton);
    };
});
