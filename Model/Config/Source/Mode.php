<?php

namespace Airwallex\Payments\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    public const DEMO = 'demo';
    private const PRODUCTION = 'prod';

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::DEMO,
                'label' => __('Demo')
            ],
            [
                'value' => self::PRODUCTION,
                'label' => __('Production')
            ],
        ];
    }
}
