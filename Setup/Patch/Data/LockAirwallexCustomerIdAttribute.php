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
namespace Airwallex\Payments\Setup\Patch\Data;

use Airwallex\Payments\Model\PaymentConsents;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Cache\Type as Eav;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class LockAirwallexCustomerIdAttribute implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private CacheInterface $cache;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup, CacheInterface $cache)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->cache = $cache;
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from(['ea' => $this->moduleDataSetup->getTable('eav_attribute')], ['attribute_id'])
            ->join(
                ['et' => $this->moduleDataSetup->getTable('eav_entity_type')],
                'ea.entity_type_id = et.entity_type_id',
                []
            )
            ->where('et.entity_type_code = ?', Customer::ENTITY)
            ->where('ea.attribute_code = ?', PaymentConsents::KEY_AIRWALLEX_CUSTOMER_ID);

        $attributeIds = array_map('intval', $connection->fetchCol($select));
        if (!empty($attributeIds)) {
            $connection->update(
                $this->moduleDataSetup->getTable('customer_eav_attribute'),
                ['is_system' => 1],
                ['attribute_id IN (?)' => $attributeIds]
            );
            $connection->delete(
                $this->moduleDataSetup->getTable('customer_form_attribute'),
                ['attribute_id IN (?)' => $attributeIds]
            );
            // Direct SQL updates bypass the normal attribute metadata cache invalidation.
            $this->cache->clean([Eav::CACHE_TAG]);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddAirwallexCustomerIdAttribute::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
