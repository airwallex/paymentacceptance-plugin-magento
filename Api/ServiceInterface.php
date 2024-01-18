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
namespace Airwallex\Payments\Api;

interface ServiceInterface
{
    /**
     * @param string $method
     *
     * @return string
     */
    public function createIntent(string $method, string $powSolution): string;

    /**
     * @return string
     */
    public function redirectUrl(): string;

    /**
     * @param string $intentId
     * @param string $method
     *
     * @return string
     */
    public function refreshIntent(string $intentId, string $method, string $powSolution): string;
}
