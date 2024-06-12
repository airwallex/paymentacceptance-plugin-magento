<?php

namespace Airwallex\Payments\Model\Ui;

use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Airwallex\Payments\Model\Methods\Vault;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    protected TokenUiComponentInterfaceFactory $componentFactory;

    /**
     * TokenUiComponentProvider constructor
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory
    ) {
        $this->componentFactory = $componentFactory;
    }

    /**
     * Get UI component for token
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $component = $this->componentFactory->create(
            [
                'config' => [
                    'code' => Vault::CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Airwallex_Payments/js/view/payment/method-renderer/vault'
            ]
        );

        return $component;
    }
}
