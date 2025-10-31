<?php

namespace Airwallex\Payments\Controller\Adminhtml\Configuration;

use Airwallex\PayappsPlugin\CommonLibrary\Struct\PaymentMethodType;
use Airwallex\PayappsPlugin\CommonLibrary\UseCase\PaymentMethodType\GetList as GetPaymentMethodTypeList;
use Airwallex\Payments\CommonLibraryInit;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;

class GetPaymentMethodTypeStatus extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected RequestInterface $request;
    protected GetPaymentMethodTypeList $getPaymentMethodTypeList;

    public function __construct(
        Context                  $context,
        JsonFactory              $resultJsonFactory,
        RequestInterface         $request,
        CommonLibraryInit        $commonLibraryInit,
        GetPaymentMethodTypeList $getPaymentMethodTypeList
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->getPaymentMethodTypeList = $getPaymentMethodTypeList;
        $commonLibraryInit->exec();
    }

    /**
     * Set Apple Pay domain
     *
     * @return Json
     * @throws Exception
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $method = $this->request->getParam('method');
        if (!$method) {
            throw new Exception('Method parameter is required.');
        }
        /** @var GetPaymentMethodTypeList $getPaymentMethodTypes */
        $paymentMethodTypes = $this->getPaymentMethodTypeList
            ->setTransactionMode(PaymentMethodType::PAYMENT_METHOD_TYPE_ONE_OFF)
            ->setCacheTime(30)
            ->get();
        foreach ($paymentMethodTypes as $paymentMethodType) {
            /** @var PaymentMethodType $paymentMethodType */
            if ($paymentMethodType->getName() == $method) {
                $resultJson->setData($paymentMethodType->isActive() ? 'active' : 'inactive');
                return $resultJson;
            }
        }
        $resultJson->setData('inactive');
        return $resultJson;
    }
}
