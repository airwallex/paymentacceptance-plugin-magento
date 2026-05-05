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
namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class ExpressCheckoutLayout implements ObserverInterface
{

    protected RequestInterface $request;
    private Configuration $configuration;

    public function __construct(
        RequestInterface $request,
        Configuration    $configuration
    )
    {
        $this->request = $request;
        $this->configuration = $configuration;
    }

    public function execute(Observer $observer)
    {
        if (!$this->configuration->isExpressActive()) {
            return;
        }

        $fullActionName = $this->request->getFullActionName();
        $enabledAreas = $this->configuration->expressDisplayArea();

        $isCartPage = $fullActionName === 'checkout_cart_index';
        $isProductPage = $fullActionName === 'catalog_product_view';
        $isCheckoutPage = $fullActionName === 'checkout_index_index';

        $isCartPageEnabled = strpos($enabledAreas, 'cart_page') !== false;
        $isProductPageEnabled = strpos($enabledAreas, 'product_page') !== false;
        $isMinicartEnabled = strpos($enabledAreas, 'minicart') !== false;
        $isCheckoutPageEnabled = strpos($enabledAreas, 'checkout_page') !== false;

        $shouldSkipMinicart = ($isCartPage && $isCartPageEnabled) || ($isProductPage && $isProductPageEnabled);

        $layoutUpdate = $observer->getLayout()->getUpdate();

        if ($isMinicartEnabled && !$shouldSkipMinicart) {
            $layoutUpdate->addHandle('minicart_express');
        }

        if ($isCheckoutPage && $isCheckoutPageEnabled) {
            $layoutUpdate->addHandle('checkout_page_express');
        }
    }
}
