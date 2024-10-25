<?php

namespace Airwallex\Payments\Controller\Adminhtml\Configuration;

use Magento\Framework\Math\Random;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;

class SetUpdateSettingsMessage extends Action
{
    public const CACHE_NAME = 'airwallex_set_update_settings_message';

    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected StoreManager $storeManager;
    protected Random $random;
    protected CacheInterface $cache;
    protected RequestInterface $request;

    public function __construct(
        Context          $context,
        JsonFactory      $resultJsonFactory,
        StoreManager     $storeManager,
        Random           $random,
        CacheInterface   $cache,
        RequestInterface $request
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->random = $random;
        $this->cache = $cache;
        $this->request = $request;
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $this->context->getMessageManager()->addSuccessMessage('Your Airwallex plug-in is activated.
            You can also manage which account is connected to your Magento store.');
        header('Location: ' . base64_decode($this->request->getParam('target_url')));
        return $resultJson;
    }
}
