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
namespace Airwallex\Payments\Model\Client\Request\PaymentConsent;

use Airwallex\Payments\Api\Data\SavedPaymentResponseInterface;
use Airwallex\Payments\Api\Data\SavedPaymentResponseInterfaceFactory;
use Airwallex\Payments\Helper\AuthenticationHelper;
use Airwallex\Payments\Helper\Configuration;
use Airwallex\Payments\Logger\Guzzle\RequestLogger;
use Airwallex\Payments\Model\Client\AbstractClient;
use Airwallex\Payments\Model\Client\Interfaces\BearerAuthenticationInterface;
use Airwallex\Payments\Model\SavedPaymentResponse;
use Magento\Framework\DataObject\IdentityService;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Http\Message\ResponseInterface;

class GetList extends AbstractClient implements BearerAuthenticationInterface
{
    const TRIGGER_REASON_SCHEDULED = 'scheduled';
    const TRIGGER_REASON_UNSCHEDULED = 'unscheduled';
    const TRIGGERED_BY_CUSTOMER = 'customer';
    const TRIGGERED_BY_MERCHANT = 'merchant';

    private SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory;

    public function __construct(
        AuthenticationHelper $authenticationHelper,
        IdentityService $identityService,
        RequestLogger $requestLogger,
        Configuration $configuration,
        ModuleListInterface $moduleList,
        SavedPaymentResponseInterfaceFactory $savedPaymentResponseFactory
    ) {
        parent::__construct($authenticationHelper, $identityService, $requestLogger, $configuration, $moduleList);
        $this->savedPaymentResponseFactory = $savedPaymentResponseFactory;
    }

    /**
     * @return string
     */
    protected function getMethod(): string
    {
        return "GET";
    }

    /**
     * @return string
     */
    protected function getUri(): string
    {
        return 'pa/payment_consents';
    }

    /**
     * @param string $airwallexCustomerId
     * @return AbstractClient|GetList
     */
    public function setCustomerId(string $airwallexCustomerId)
    {
        return $this->setParam('customer_id', $airwallexCustomerId);
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     * @return AbstractClient|GetList
     */
    public function setPage(int $pageNumber, int $pageSize = 20)
    {
        return $this->setParam('page_num', $pageNumber)
            ->setParam('page_size', $pageSize);
    }

    /**
     * @param string $status
     * @return AbstractClient|GetList
     */
    public function setStatus(string $status)
    {
        return $this->setParam('status', $status);
    }

    /**
     * @param string $triggerReason
     * @return AbstractClient|GetList
     */
    public function setTriggerReason(string $triggerReason)
    {
        return $this->setParam('merchant_trigger_reason', $triggerReason);
    }

    /**
     * @param string $triggeredBy
     * @return AbstractClient|GetList
     */
    public function setNextTriggeredBy(string $triggeredBy)
    {
        return $this->setParam('next_triggered_by', $triggeredBy);
    }

    /**
     * @param ResponseInterface $request
     *
     * @return SavedPaymentResponseInterface[]
     * @throws \JsonException
     */
    protected function parseResponse(ResponseInterface $request)
    {
        $request = $this->parseJson($request);

        $result = [];
        foreach ($request->items as $item) {
            if (!property_exists($item, 'payment_method')
                || !property_exists($item->payment_method, 'card')) {
                continue;
            }

            /** @var SavedPaymentResponse $result */
            $savedPayment = $this->savedPaymentResponseFactory->create();
            $savedPayment->setData([
                SavedPaymentResponseInterface::DATA_KEY_ID => $item->id,
                SavedPaymentResponseInterface::DATA_KEY_CARD_BRAND => $item->payment_method->card->brand,
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_MONTH => $item->payment_method->card->expiry_month,
                SavedPaymentResponseInterface::DATA_KEY_CARD_EXPIRY_YEAR => $item->payment_method->card->expiry_year,
                SavedPaymentResponseInterface::DATA_KEY_CARD_LAST_FOUR => $item->payment_method->card->last4,
                SavedPaymentResponseInterface::DATA_KEY_CARD_HOLDER_NAME => $item->payment_method->card->name
            ]);

            $result[] = $savedPayment;
        }

        return $result;
    }
}
