<?php
/**
 * This file is part of the Airwallex Payments module.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * to newer versions in the future.
 *
 * @copyright Copyright (c) 2021 Magebit, Ltd. (https://magebit.com/)
 * @license   GNU General Public License ("GPL") v3.0
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
