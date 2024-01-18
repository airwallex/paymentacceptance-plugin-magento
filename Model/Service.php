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
use Airwallex\Payments\Helper\Verification as VerificationHelper;
use Airwallex\Payments\Model\Methods\CardMethod;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Helper\Data as CheckoutData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Serialize\SerializerInterface;

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
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Index constructor.
     *
     * @param SerializerInterface $serializer
     * @param PaymentIntents $paymentIntents
     * @param Configuration $configuration
     * @param CheckoutData $checkoutHelper
     */
    public function __construct(
        SerializerInterface $serializer,
        PaymentIntents $paymentIntents,
        Configuration $configuration,
        CheckoutData $checkoutHelper,
        protected VerificationHelper $verificationHelper
    ) {
        $this->serializer = $serializer;
        $this->paymentIntents = $paymentIntents;
        $this->configuration = $configuration;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * Creates payment intent
     *
     * @param string $method
     * @param string $powSolution
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createIntent(string $method, string $powSolution): string
    {
        $this->verificationHelper->validatePOWSolution($powSolution);

        return $this->createNewIntent($method);
    }

    protected function createNewIntent(string $method): string
    {
        $response = $this->paymentIntents->getIntents();
        $response['mode'] = $this->configuration->getMode();
        $response = array_merge($response, $this->getExtraConfiguration($method));

        return $this->serializer->serialize($response);
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
     * Checks if payment should be captured on order placement
     *
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
     * Regenerates payment intent
     *
     * @param string $intentId
     * @param string $method
     * @param string $powSolution
     * @return string
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function refreshIntent(string $intentId, string $method, string $powSolution): string
    {
        $this->verificationHelper->validatePOWSolution($powSolution);

        $this->paymentIntents->cancelIntent($intentId);

        return $this->createNewIntent($method);
    }
}
