<?php

namespace Airwallex\PayappsPlugin\CommonLibrary\UseCase\Webhook;

use Airwallex\PayappsPlugin\CommonLibrary\Configuration\Webhook as WebhookConfig;
use Airwallex\PayappsPlugin\CommonLibrary\Exception\RequestException;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\AbstractApi;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook\Create;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook\Delete;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook\GetList;
use Airwallex\PayappsPlugin\CommonLibrary\Gateway\AWXClientAPI\Webhook\Retrieve;
use Airwallex\PayappsPlugin\CommonLibrary\Logger\FileLogger;
use Airwallex\PayappsPlugin\CommonLibrary\Struct\Webhook;
use Exception;
use Throwable;

class Provisioner
{
    /** @param callable|null $requestIdForCreate Persists and returns the creation request ID. */
    public function provision(string $url, string $requestId, string $preferredWebhookId = '', $requestIdForCreate = null): Webhook
    {
        try {
            $url = $this->normalizeUrl($url);
            if ($url === '' || !$this->isValidRequestId($requestId)
                || ($requestIdForCreate !== null && !is_callable($requestIdForCreate))) {
                throw new Exception('HTTPS URL and request ID are required.');
            }
            list($matches, $usedIds) = $this->findWebhooks($url);
            usort($matches, function (Webhook $left, Webhook $right) use ($preferredWebhookId) {
                if (($left->getId() === $preferredWebhookId) !== ($right->getId() === $preferredWebhookId)) {
                    return $left->getId() === $preferredWebhookId ? -1 : 1;
                }
                return strcmp($left->getId(), $right->getId());
            });
            foreach ($matches as $match) {
                try {
                    $webhook = (new Retrieve())->setWebhookId($match->getId())->send();
                } catch (RequestException $e) {
                    if ($this->isNotFound($e)) {
                        continue;
                    }
                    throw $e;
                }
                if ($webhook->getId() !== $match->getId()) {
                    throw new Exception('Unexpected webhook ID.');
                }
                if ($this->isCompatible($webhook, $url) && $webhook->getSecret() !== '') {
                    return $webhook;
                }
                $usedIds[$webhook->getRequestId()] = true;
            }
            if ($requestIdForCreate !== null) {
                $requestId = $requestIdForCreate();
                if (!$this->isValidRequestId($requestId)) {
                    throw new Exception('Creation request ID is required.');
                }
            }
            if (isset($usedIds[$requestId])) {
                throw new Exception('Creation request ID is already in use.');
            }
            $webhook = (new Create())->setUrl($url)->setVersion(AbstractApi::X_API_VERSION)
                ->setEvents(WebhookConfig::PAYMENT_AND_REFUND_EVENTS)->setRequestId($requestId)->send();
            if ($webhook->getSecret() === '' && $webhook->getId() !== '') {
                $webhook = (new Retrieve())->setWebhookId($webhook->getId())->send();
            }
            if (!$this->isCompatible($webhook, $url) || $webhook->getSecret() === '') {
                throw new Exception('Incomplete webhook configuration.');
            }
            $webhook->setRequestId($requestId);
            return $webhook;
        } catch (Throwable $e) {
            FileLogger::error('Webhook provisioning failed.');
            throw new Exception('Could not provision the webhook. Check API credentials, webhook permissions and required event subscriptions.');
        }
    }

    private function findWebhooks(string $url): array
    {
        $matches = [];
        $usedIds = [];
        $page = '';
        $seen = [];
        do {
            if (isset($seen[$page]) || count($seen) >= 100) {
                throw new Exception('Incomplete webhook list.');
            }
            $seen[$page] = true;
            $request = (new GetList())->setStrict(true)->setPageSize(100);
            if ($page !== '') {
                $request->setPage($page);
            }
            $list = $request->send();
            foreach ($list->getItems() as $item) {
                if ($this->isCompatible($item, $url)) {
                    $matches[] = $item;
                } else {
                    $usedIds[$item->getRequestId()] = true;
                }
            }
            $page = $list->getPageAfter();
        } while ($page !== '');
        return [$matches, $usedIds];
    }

    private function isCompatible(Webhook $webhook, string $url): bool
    {
        return $webhook->getId() !== '' && $this->normalizeUrl($webhook->getUrl()) === $url
            && $webhook->getVersion() === AbstractApi::X_API_VERSION
            && !array_diff(WebhookConfig::PAYMENT_AND_REFUND_EVENTS, $webhook->getEvents());
    }

    private function normalizeUrl(string $url): string
    {
        $parts = !preg_match('/[\s\x00-\x1F\x7F]/', $url) && filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : false;
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $bareHost = rtrim($host, '.');
        $ip = trim($host, '[]');
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $bareHost === ''
            || $bareHost === 'localhost' || substr($bareHost, -10) === '.localhost'
            || array_intersect_key($parts, ['user' => 1, 'pass' => 1, 'fragment' => 1])
            || (filter_var($ip, FILTER_VALIDATE_IP) && !filter_var(inet_ntop(inet_pton($ip)), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            return '';
        }
        $port = isset($parts['port']) && (int) $parts['port'] !== 443 ? ':' . (int) $parts['port'] : '';
        return 'https://' . $host . $port . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    private function isValidRequestId($id): bool
    {
        return is_string($id) && $id !== '' && strlen($id) <= 50 && !preg_match('/[\x00-\x1F\x7F]/', $id);
    }

    private function isNotFound(Throwable $e): bool
    {
        return $e instanceof RequestException
            && (((array) json_decode($e->getMessage(), true))['code'] ?? '') === AbstractApi::ERROR_RESOURCE_NOT_FOUND;
    }

    public function remove(string $webhookId)
    {
        try {
            if ($webhookId === '' || !(new Delete())->setWebhookId($webhookId)->send()->isDeleted()) {
                throw new Exception('Webhook was not deleted.');
            }
        } catch (Throwable $e) {
            if (!$this->isNotFound($e)) {
                FileLogger::error('Webhook removal failed.');
                throw new Exception('Could not remove the webhook. Check API credentials and webhook permissions.');
            }
        }
    }
}
