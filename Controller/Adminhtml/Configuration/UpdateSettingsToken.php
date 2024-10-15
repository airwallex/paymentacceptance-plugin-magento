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

class UpdateSettingsToken extends Action
{
    public const CACHE_NAME = 'airwallex_update_settings_token';

    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected StoreManager $storeManager;
    protected Random $random;
    protected CacheInterface $cache;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        StoreManager $storeManager,
        Random $random,
        CacheInterface $cache
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->random = $random;
        $this->cache = $cache;
    }

    /**
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        
        $token = $this->random->getRandomString(32);
        $this->cache->save($token, self::CACHE_NAME, [], 60 * 60 * 24);

        $resultJson->setData(compact('token'));

        return $resultJson;
    }
}
