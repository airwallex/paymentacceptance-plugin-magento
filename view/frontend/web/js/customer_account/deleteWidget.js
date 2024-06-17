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
