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

declare(strict_types=1);

namespace Airwallex\Payments\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\View\Result\PageFactory;

class SavedCards implements HttpGetActionInterface
{
    private PageFactory $resultPageFactory;
    private Session $customerSession;
    private Http $response;

    public function __construct(
        Session $customerSession,
        PageFactory $resultPageFactory,
        Http $response
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->response = $response;
    }

    /**
     * @return Http|ResponseInterface|ResultInterface
     * @throws SessionException
     */
    public function execute()
    {
        if ($this->customerSession->authenticate()) {
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(__('Saved Cards'));

            return $resultPage;
        }

        return $this->response;
    }
}
