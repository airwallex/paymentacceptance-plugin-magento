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
namespace Airwallex\Payments\Model\Config\Source\Express\ApplePay;

class ButtonType
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'add-money',
                'label' => __('Add money')
            ],
            [
                'value' => 'book',
                'label' => __('Book')
            ],
            [
                'value' => 'buy',
                'label' => __('Buy')
            ],
            [
                'value' => 'check-out',
                'label' => __('Check-out')
            ],
            [
                'value' => 'continue',
                'label' => __('Continue')
            ],
            [
                'value' => 'contribute',
                'label' => __('Contribute')
            ],
            [
                'value' => 'donate',
                'label' => __('Donate')
            ],
            [
                'value' => 'order',
                'label' => __('Order')
            ],
            // [
            //     'value' => 'plain',
            //     'label' => __('Plain')
            // ],
            [
                'value' => 'reload',
                'label' => __('Reload')
            ],
            [
                'value' => 'rent',
                'label' => __('Rent')
            ],
            [
                'value' => 'subscribe',
                'label' => __('Subscribe')
            ],
            [
                'value' => 'support',
                'label' => __('Support')
            ],
            [
                'value' => 'tip',
                'label' => __('Tip')
            ],
            [
                'value' => 'top-up',
                'label' => __('Top-up')
            ]
        ];
    }
}
