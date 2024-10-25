<?php

namespace Airwallex\Payments\Controller\Adminhtml\Configuration;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Math\Random;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Airwallex\Payments\Helper\AvailablePaymentMethodsHelper;
use Airwallex\Payments\Model\Client\Request\ApplePayDomain\GetList;
use Airwallex\Payments\Model\Client\Request\ApplePayDomain\Add;

class PayEnable extends Action
{
    protected JsonFactory $resultJsonFactory;
    protected Context $context;
    protected StoreManager $storeManager;
    protected Random $random;
    protected CacheInterface $cache;
    protected RequestInterface $request;
    protected DirectoryList $directoryList;
    protected AvailablePaymentMethodsHelper $availablePaymentMethodsHelper;
    protected GetList $appleDomainList;
    protected Add $appleDomainAdd;

    public function __construct(
        Context                       $context,
        JsonFactory                   $resultJsonFactory,
        StoreManager                  $storeManager,
        Random                        $random,
        CacheInterface                $cache,
        RequestInterface              $request,
        DirectoryList                 $directoryList,
        AvailablePaymentMethodsHelper $availablePaymentMethodsHelper,
        GetList                       $appleDomainList,
        Add                           $appleDomainAdd
    )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->random = $random;
        $this->cache = $cache;
        $this->request = $request;
        $this->directoryList = $directoryList;
        $this->availablePaymentMethodsHelper = $availablePaymentMethodsHelper;
        $this->appleDomainList = $appleDomainList;
        $this->appleDomainAdd = $appleDomainAdd;
    }

    protected function getHost()
    {
        $host = trim($this->storeManager->getStore()->getBaseUrl(), '/');
        $host = str_replace('http://', '', $host);
        return str_replace('https://', '', $host);
    }

    private function methodInactiveTip($type): string
    {
        $link = "<a href='https://demo.airwallex.com/app/acquiring/payment-methods/other-pms'
                    style='color: red; font-weight: 600; text-decoration: underline;' target='_blank'>Airwallex</a>";
        return 'You have not activated ' . $type . ' as a payment method.
                 Please go to ' . $link . ' to activate ' . $type . ' before try again.';
    }

    private function fileUploadFailedTip(): string
    {
        $link = "<a href='https://demo.airwallex.com/app/acquiring/settings/apple-pay/add-domain'
                    style='color: red; font-weight: 600; text-decoration: underline;' target='_blank'>download the file</a>";
        return 'We could not add the domain file to your server. Please ' . $link . ' and host it on your
            site at the following path: &lt;&lt;DOMAIN_NAME&gt;&gt;/.well-known/apple-developer-merchantid-domain-association';
    }

    public function error($message): array
    {
        return [
            'type' => 'error',
            'message' => __($message)
        ];
    }

    public function success(): array
    {
        return [
            'type' => 'success',
            'message' => __('succeed')
        ];
    }

    /**
     * Set apple pay domain
     *
     * @return Json
     * @throws GuzzleException
     * @throws JsonException
     * @throws FileSystemException
     * @throws Exception
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $methods = $this->request->getParam('methods');
        $host = $this->getHost();
        $types = $this->availablePaymentMethodsHelper->getLatestItems();
        $isApplePayActive = false;
        $isGooglePayActive = false;
        if (empty($methods)) {
            $resultJson->setData($this->error('post parameter methods is required'));
            return $resultJson;
        }
        foreach ($types as $type) {
            if (strstr($methods, 'apple_pay') && $type['name'] === 'applepay' && $type['active'] === true) {
                $isApplePayActive = true;
            }
            if (strstr($methods, 'google_pay') && $type['name'] === 'googlepay' && $type['active'] === true) {
                $isGooglePayActive = true;
            }
        }
        if (strstr($methods, 'apple_pay') && !$isApplePayActive) {
            $resultJson->setData($this->error($this->methodInactiveTip('Apple Pay')));
            return $resultJson;
        }

        if (strstr($methods, 'google_pay') && !$isGooglePayActive) {
            $resultJson->setData($this->error($this->methodInactiveTip('Google Pay')));
            return $resultJson;
        }

        if (empty(strstr($methods, 'apple_pay'))) return $resultJson;
        $list = $this->appleDomainList->send();
        if (in_array($host, $list, true)) {
            $resultJson->setData($this->success());
            return $resultJson;
        }
        if (!$this->uploadAppleDomainFile()) {
            $resultJson->setData($this->error($this->fileUploadFailedTip()));
            return $resultJson;
        }

        $list = $this->appleDomainAdd->setDomain($host)->send();
        if (in_array($host, $list, true)) {
            $resultJson->setData($this->success());
            return $resultJson;
        }

        $link = "<a href='https://demo.airwallex.com/app/acquiring/settings/apple-pay/add-domain'
                    style='color: red; font-weight: 600; text-decoration: underline;' target='_blank'>Airwallex</a>";
        $tip = "We could not register your domain. Please go to $link to specify the domain names that you’ll register with Apple before trying again.";
        $resultJson->setData($this->error($tip));
        return $resultJson;
    }

    /**
     * @return bool
     * @throws FileSystemException
     */
    public function uploadAppleDomainFile(): bool
    {
        $filename = 'apple-developer-merchantid-domain-association';
        $destinationDir = $this->directoryList->getPath(DirectoryList::PUB) . '/.well-known/';

        if (file_exists($destinationDir . $filename)) return true;
        if (!is_dir($destinationDir)) {
            try {
                mkdir($destinationDir, 0755, true);
            } catch (Exception $e) {
                return false;
            }
        }
        $sourceFile = $this->directoryList->getPath(DirectoryList::APP) . '/../vendor/airwallex/payments-plugin-magento/' . $filename;
        $destinationFile = $destinationDir . $filename;
        try {
            copy($sourceFile, $destinationFile);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }
}
