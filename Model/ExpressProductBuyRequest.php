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
namespace Airwallex\Payments\Model;

class ExpressProductBuyRequest
{
    private const ALLOWED_KEYS = [
        'product',
        'qty',
        'super_attribute',
        'options',
        'bundle_option',
        'bundle_option_qty',
        'links',
        'super_group',
        'giftcard_amount',
        'custom_giftcard_amount',
        'giftcard_sender_name',
        'giftcard_sender_email',
        'giftcard_recipient_name',
        'giftcard_recipient_email',
        'giftcard_message',
    ];

    /**
     * Copy only storefront product-option keys.
     *
     * @param array $params
     * @return array
     */
    public function filter(array $params): array
    {
        $buyRequest = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (array_key_exists($key, $params)) {
                $buyRequest[$key] = $params[$key];
            }
        }
        return $buyRequest;
    }

    /**
     * Whether the product is assigned to the current website.
     *
     * Website ids from the catalog are strings. Compare as ints so "1" matches 1
     * and a string id cannot satisfy a different website through loose comparison.
     *
     * @param mixed $websiteIds
     * @param int $websiteId
     * @return bool
     */
    public function isAssignedToWebsite($websiteIds, int $websiteId): bool
    {
        return in_array($websiteId, array_map('intval', (array)$websiteIds), true);
    }
}
