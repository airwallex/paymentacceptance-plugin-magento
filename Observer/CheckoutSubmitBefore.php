<?php

namespace Airwallex\Payments\Observer;

use Airwallex\Payments\Model\Client\Request\PaymentMethod\Get;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Airwallex\Payments\Helper\IsOrderCreatedHelper;

class CheckoutSubmitBefore implements ObserverInterface
{
    /**
     * @throws GuzzleException
     * @throws CouldNotSaveException
     * @throws JsonException
     */
    public function execute(Observer $observer): void
    {
        $request = ObjectManager::getInstance()->get(RequestHttp::class);
        $content = $request->getContent();
        $arr = json_decode($content, true);
        if (empty($arr['paymentMethodId'])) return;
        $paymentMethodId = $arr['paymentMethodId'];
        $paymentMethodDetail = ObjectManager::getInstance()->get(Get::class);

        // 这里打印卡的前六后四，过期时间等信息
        ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class)->debug(
            $paymentMethodDetail->setPaymentMethodId($paymentMethodId)->send()
        );
        $isValid = false;
        if (!$isValid) { // 如果 forter 未通过，弹出提示信息
            throw new CouldNotSaveException(__("Card is not valid"));
        }
    }
}
