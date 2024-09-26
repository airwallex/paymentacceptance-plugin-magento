<?php

namespace Airwallex\Payments\Block\Customer;

use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;
use Magento\Vault\Block\CardRendererInterface;
use Magento\Payment\Model\CcConfigProvider;
use Airwallex\Payments\Model\Methods\Vault;
use Airwallex\Payments\Model\Traits\HelperTrait;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Airwallex\Payments\Api\PaymentConsentsInterface;

/**
 * @api
 * @since 100.0.2
 */
class CardRenderer extends AbstractTokenRenderer implements CardRendererInterface
{
    use HelperTrait;

    protected Session $customerSession;
    protected CustomerRepositoryInterface $customerRepository;
    protected PaymentConsentsInterface $paymentConsents;

    /**
     * @var CcConfigProvider
     */
    private CcConfigProvider $configProvider;

    /**
     * @param Context $context
     * @param CcConfigProvider $configProvider
     * @param Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param PaymentConsentsInterface $paymentConsents
     * @param array $data
     */
    public function __construct(
        Context $context,
        CcConfigProvider $configProvider,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        PaymentConsentsInterface $paymentConsents,
        array $data = []
) {
        parent::__construct($context, $data);

        $this->configProvider = $configProvider;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->paymentConsents = $paymentConsents;
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
        return $this->getTokenDetails()['maskedCC'] ?? '';
    }

    public function getBrand()
    {
        return $this->getTokenDetails()['type'] ?? '';
    }

    public function getStatus()
    {
        return $this->getTokenDetails()['status'] ?? '';
    }

    public function cardCustomerId()
    {
        return $this->getTokenDetails()['customer_id'] ?? '';
    }

    public function currentCustomerId(): string
    {
        return $this->paymentConsents->getAirwallexCustomerIdInDB($this->customerSession->getId());
    }

    /**
     * Get exp Date
     *
     * @return string
     */
    public function getExpDate(): string
    {
        return $this->getTokenDetails()['expirationDate'] ?? '';
    }

    /**
     * Get Icon
     *
     * @return ?array
     */
    public function icon(): ?array
    {
        return $this->getIconForType($this->convertCcType($this->getBrand()));
    }

    /**
     * Get Icon Url
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        return $this->icon()['url'] ?? '';
    }

    /**
     * Get Icon Height
     *
     * @return int
     */
    public function getIconHeight(): int
    {
        return $this->icon()['height'] ?? '';
    }

    /**
     * Get Icon Width
     *
     * @return int
     */
    public function getIconWidth(): int
    {
        return $this->icon()['width'] ?? '';
    }

    /**
     * @param string $type
     * @return array
     * @since 100.1.0
     */
    private function getIconForType(string $type): array
    {
        $arr = $this->configProvider->getIcons()[strtoupper($this->convertCcType($type))] ?? [
            'url' => '',
            'width' => 0,
            'height' => 0
        ];
        if (empty($arr['url']) && strtoupper($this->convertCcType($type)) === "UN") {
            $arr['url'] = 'https://checkout.airwallex.com/static/media/unionpay.9421a757c6289e8c65ec.svg';
            $arr['width'] = 46;
            $arr['height'] = 30;
        }
        return $arr;
    }
}
