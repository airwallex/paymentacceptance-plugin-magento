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

use Airwallex\Payments\Logger\Logger;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Create;
use Airwallex\Payments\Model\Client\Request\PaymentIntents\Cancel;
use Airwallex\Payments\Model\Methods\AbstractMethod;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class PaymentIntents
{
    private const CACHE_TIME = 60;

    /**
     * @var Create
     */
    private Create $paymentIntentsCreate;

    /**
     * @var Cancel
     */
    private Cancel $paymentIntentsCancel;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var QuoteRepository
     */
    private QuoteRepository $quoteRepository;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlInterface;

    public function __construct(
        Create $paymentIntentsCreate,
        Cancel $paymentIntentsCancel,
        Session $checkoutSession,
        SerializerInterface $serializer,
        QuoteRepository $quoteRepository,
        CacheInterface $cache,
        UrlInterface $urlInterface,
        Logger $logger
    ) {
        $this->paymentIntentsCancel = $paymentIntentsCancel;
        $this->paymentIntentsCreate = $paymentIntentsCreate;
        $this->checkoutSession = $checkoutSession;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->urlInterface = $urlInterface;
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getIntents(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $cacheKey = $this->getCacheKey($quote);

        if ($response = $this->cache->load($cacheKey)) {
            return $this->serializer->unserialize($response);
        }

        $this->saveQuote($quote);
        $returnUrl = $this->urlInterface->getUrl('checkout/onepage/success');

        try {
            $response = $this->paymentIntentsCreate
                ->setQuote($quote, $returnUrl)
                ->send();
        } catch (GuzzleException $exception) {
            $this->logger->quoteError($quote, 'intents', $exception->getMessage());
            throw $exception;
        }

        $this->cache->save(
            $this->serializer->serialize($response),
            $cacheKey,
            AbstractMethod::CACHE_TAGS,
            self::CACHE_TIME
        );

        return $response;
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function removeIntents(): void
    {
        $this->cache->remove($this->getCacheKey($this->checkoutSession->getQuote()));
    }

    /**
     * @param Quote $quote
     *
     * @return string
     */
    private function getCacheKey(Quote $quote): string
    {
        return 'airwallex-intent-' . $quote->getId();
    }

    /**
     * @param Quote $quote
     *
     * @return void
     */
    private function saveQuote(Quote $quote): void
    {
        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();

            $this->quoteRepository->save($quote);
        }
    }

    /**
     * @param string $intentId
     * @return mixed
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function cancelIntent(string $intentId)
    {
        try {
            $response = $this->paymentIntentsCancel
                ->setPaymentIntentId($intentId)
                ->send();
        } catch (GuzzleException $exception) {
            $quote = $this->checkoutSession->getQuote();
            $this->logger->quoteError($quote, 'intents', $exception->getMessage());
            throw $exception;
        }
        $this->removeIntents();
        return $response;
    }
}
