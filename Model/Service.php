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
namespace Airwallex\Payments\Model;

use Airwallex\Payments\Api\ServiceInterface;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Model\Methods\CardMethod;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Zend_Json;

class Service implements ServiceInterface
{
    /**
     * @var PaymentIntents
     */
    private PaymentIntents $paymentIntents;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var CheckoutData
     */
    private CheckoutData $checkoutHelper;

    /**
     * Index constructor.
     *
     * @param PaymentIntents $paymentIntents
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     */
    public function __construct(
        PaymentIntents $paymentIntents,
        Configuration $configuration,
        CheckoutData $checkoutHelper
    ) {
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * @param string $method
     *
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createIntent(string $method): string
    {
        $response = $this->paymentIntents->getIntents();
        $response['mode'] = $this->configuration->getMode();
        $response = array_merge($response, $this->getExtraConfiguration($method));

        return Zend_Json::encode($response);
    }

    /**
     * Return URL
     *
     * @return string
     * @throws LocalizedException
     */
    public function redirectUrl(): string
    {
        $checkout = $this->checkoutHelper->getCheckout();

        if (empty($checkout->getLastRealOrderId())) {
            throw new LocalizedException(__("Sorry, the order could not be placed. Please contact us for more help."));
        }

        return  $checkout->getAirwallexPaymentsRedirectUrl();
    }

    /**
     * @param string $method
     *
     * @return array
     */
    private function getExtraConfiguration(string $method): array
    {
        $data = [];

        if ($method === CardMethod::CODE) {
            $paymentAction = $this->configuration->getCardPaymentAction();
            $data['card']['auto_capture'] =  $paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE;
        }

        return $data;
    }

    /**
     * @param string $intentId
     * @param string $method
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function refreshIntent(string $intentId, string $method): string
    {
        $this->paymentIntents->cancelIntent($intentId);
        return $this->createIntent($method);
    }
}
