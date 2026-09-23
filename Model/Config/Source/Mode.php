<?php
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
namespace Airwallex\Payments\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    /**
     * Legacy sandbox identifier. Kept as the stored `mode` value and as the
     * prefix of the persisted config keys (e.g. demo_client_id) for backward
     * compatibility with existing installs.
     */
    public const DEMO = 'demo';

    /**
     * Canonical sandbox environment identifier used when talking to the
     * Airwallex API / Common Library.
     */
    public const SANDBOX = 'sandbox';

    public const PRODUCTION = 'prod';

    /**
     * Normalize a stored/legacy environment identifier to the API identifier
     * understood by the Airwallex Common Library (>= 1.31), which recognises
     * "sandbox" only. The legacy "demo" value maps to "sandbox"; anything
     * that is not a known sandbox identifier maps to "prod".
     *
     * @param string|null $env
     * @return string
     */
    public static function normalizeApiEnv(?string $env): string
    {
        return in_array($env, [self::DEMO, self::SANDBOX], true) ? self::SANDBOX : self::PRODUCTION;
    }

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::DEMO,
                // Keep the legacy value so existing saved configuration remains selected.
                'label' => __('Sandbox')
            ],
            [
                'value' => self::PRODUCTION,
                'label' => __('Production')
            ],
        ];
    }
}
