<?php

namespace Airwallex\Payments\Plugin;

use Airwallex\Payments\Model\Methods\AbstractMethod;
use Magento\Payment\Block\Form\Container;
use Magento\Payment\Model\MethodInterface;

class RemoveMethodsFromAdminReorder
{
    /**
     * @param Container $subject
     * @param array $results
     *
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetMethods(Container $subject, array $results): array
    {
        $results = array_filter($results, static function (MethodInterface $method) {
            return strpos($method->getCode(), AbstractMethod::PAYMENT_PREFIX) === false;
        });

        return array_values($results);
    }
}
