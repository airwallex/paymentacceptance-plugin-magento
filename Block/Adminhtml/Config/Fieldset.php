<?php

namespace Airwallex\Payments\Block\Adminhtml\Config;

use Magento\Config\Block\System\Config\Form\Fieldset as AdminhtmlFieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Fieldset extends AdminhtmlFieldset
{
    /**
     * Add custom css class
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getFrontendClass($element): string
    {
        return parent::_getFrontendClass($element) . ' with-button enabled';
    }

    /**
     * Return header title part of html for payment solution
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getHeaderTitleHtml($element): string
    {
        $html = '<div class="config-heading" >';

        $htmlId = $element->getHtmlId();
        $html .= '<div class="button-container"><button type="button"' .
            ' class="button action-configure' .
            '" id="' .
            $htmlId .
            '-head" onclick="awxToggleSolution.call(this, \'' .
            $htmlId .
            "', '" .
            $this->getUrl(
                'adminhtml/*/state'
            ) . '\'); return false;"><span class="state-closed">' . __(
                'Configure'
            ) . '</span><span class="state-opened">' . __(
                'Close'
            ) . '</span></button>';

        $html .= '</div>';
        $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';

        if ($comment = $element->getComment()) {
            $html .= '<span class="heading-intro">' . $comment . '</span>';
        }

        $html .= '<div class="config-alt"></div>';
        $html .= '</div></div>';
        $html .= "
<script>
require(['jquery', 'prototype'], function ($) {
    'use strict';
    window.awxToggleSolution = function (id, url) {
        var doScroll = false;
        var pos = false;

        Fieldset.toggleCollapse(id, url);
        if (document.querySelector('#{$htmlId}').hasClassName('open')) {
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
</script>
        ";

        return $html;
    }

    /**
     * Return header comment part of html for payment solution
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getHeaderCommentHtml($element): string
    {
        return '';
    }

    /**
     * Get collapsed state on-load
     *
     * @param AbstractElement $element
     * @return false
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _isCollapseState($element): bool
    {
        return false;
    }

    /**
     * _getExtraJs
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getExtraJs($element): string
    {
        return '';
    }
}
