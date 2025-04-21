<?php

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
