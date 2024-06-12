<?php

namespace Airwallex\Payments\Block\Customer;

use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;
use Magento\Vault\Block\CardRendererInterface;
use Magento\Payment\Model\CcConfigProvider;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\Traits\HelperTrait;

/**
 * @api
 * @since 100.0.2
 */
class CardRenderer extends AbstractTokenRenderer implements CardRendererInterface
{
    use HelperTrait;

    /**
     * @var ConfigProvider
     */
    private CcConfigProvider $configProvider;

    /**
     * @param Context $context
     * @param ConfigProvider $configProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        CcConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->configProvider = $configProvider;
    }
    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     * @return boolean
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === Vault::CODE;
    }

    /**
     * Get Number Last 4 Digits
     *
     * @return string
     */
    public function getNumberLast4Digits(): string
    {
        return $this->getTokenDetails()['maskedCC'];
    }

    /**
     * Get exp Date
     *
     * @return string
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['expirationDate'];
    }

    /**
     * Get Icon
     *
     * @return array
     */
    public function icon()
    {
        return $this->getIconForType($this->convertCcType($this->getTokenDetails()['type']));
    }

    /**
     * Get Icon Url
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        return $this->icon()['url'];
    }

    /**
     * Get Icon Height
     *
     * @return int
     */
    public function getIconHeight(): int
    {
        return $this->icon()['height'];
    }

    /**
     * Get Icon Width
     *
     * @return int
     */
    public function getIconWidth(): int
    {
        return $this->icon()['width'];
    }

    /**
     * @param string $type
     * @return array
     * @since 100.1.0
     */
    private function getIconForType(string $type): array
    {
        return $this->configProvider->getIcons()[strtoupper($this->convertCcType($type))] ?? [
            'url' => '',
            'width' => 0,
            'height' => 0
        ];
    }
}
