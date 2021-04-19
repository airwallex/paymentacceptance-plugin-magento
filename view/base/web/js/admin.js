/* global Fieldset */
require(['jquery', 'prototype'], function ($) {
    'use strict';

    /**
     * Open admin section
     * @param id
     * @param url
     */
    window.toggleSolution = function (id, url) {
        var doScroll = false;
        var pos = false;

        Fieldset.toggleCollapse(id, url);
        if ($(this).hasClassName('open')) {
            $$('.with-button button.button').each(function (anotherButton) {
                if (anotherButton !== this && $(anotherButton).hasClassName('open')) {
                    $(anotherButton).click();
                    doScroll = true;
                }
            }.bind(this));
        }

        if (doScroll) {
            pos = Element.cumulativeOffset($(this));
            window.scrollTo(pos[0], pos[1] - 45);
        }
    };
});
