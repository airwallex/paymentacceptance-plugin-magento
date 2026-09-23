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
