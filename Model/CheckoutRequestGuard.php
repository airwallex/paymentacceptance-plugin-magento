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
namespace Airwallex\Payments\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Lock\LockManagerInterface;

/** Request limits, guest caches, and CSRF validation for checkout APIs. */
class CheckoutRequestGuard
{
    private CacheInterface $cache;
    private LockManagerInterface $locks;
    private RemoteAddress $remoteAddress;
    private FormKey $formKey;
    private FormKeyValidator $formKeyValidator;

    public function __construct(
        CacheInterface $cache,
        LockManagerInterface $locks,
        RemoteAddress $remoteAddress,
        FormKey $formKey,
        FormKeyValidator $formKeyValidator
    ) {
        $this->cache = $cache;
        $this->locks = $locks;
        $this->remoteAddress = $remoteAddress;
        $this->formKey = $formKey;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * Validate express form_key against every form_key cookie (2.4.4+ can send
     * both JS and domain cookies) and fall back to Magento's session validator.
     */
    public function assertFormKey(RequestInterface $request): void
    {
        $submitted = (string) $request->getParam('form_key', '');
        if ($submitted !== '') {
            $header = method_exists($request, 'getHeader') ? (string) $request->getHeader('Cookie') : '';
            if ($header === '' && isset($_SERVER['HTTP_COOKIE']) && is_string($_SERVER['HTTP_COOKIE'])) {
                $header = $_SERVER['HTTP_COOKIE'];
            }
            if ($header !== '' && preg_match_all('/(?:^|;\s*)form_key=([^;]*)/', $header, $matches)) {
                foreach ($matches[1] as $raw) {
                    $raw = trim((string) $raw);
                    if ($raw !== '' && (hash_equals(rawurldecode($raw), $submitted) || hash_equals(urldecode($raw), $submitted))) {
                        return;
                    }
                }
            }
            if (isset($_COOKIE['form_key']) && is_string($_COOKIE['form_key']) && $_COOKIE['form_key'] !== ''
                && hash_equals($_COOKIE['form_key'], $submitted)
            ) {
                return;
            }
            if ($this->formKey->isPresent() && $this->formKeyValidator->validate($request)) {
                return;
            }
        }
        throw new LocalizedException(__('Invalid form key. Please refresh the page and try again.'));
    }

    public function limit(string $action, int $storeId, int $maximum, int $window = 300): void
    {
        // Use Magento's configured client-address resolver, never caller-supplied parameters.
        $key = $this->key('rate', $action . ':' . $storeId . ':' . $this->remoteAddress->getRemoteAddress()
            . ':' . (int) floor(time() / $window));
        $this->locked($key, function () use ($key, $maximum, $window): void {
            $count = (int) $this->cache->load($key);
            if ($count >= $maximum) {
                throw new LocalizedException(__('Too many payment requests. Please try again later.'));
            }
            if (!$this->cache->save((string) ($count + 1), $key, [], $window + 1)) {
                throw new LocalizedException(__('Payment request protection is unavailable. Please try again.'));
            }
        });
    }

    /** Store only the customer ID; client secrets are generated fresh and never cached. */
    public function guestCustomer(string $scope, callable $create): string
    {
        $key = $this->key('customer', $scope);
        return $this->locked($key, function () use ($key, $create): string {
            $id = $this->cache->load($key);
            if (is_string($id) && $id !== '') {
                return $id;
            }
            $id = $create();
            if (!is_string($id) || $id === '' || !$this->cache->save($id, $key, [], 86400)) {
                throw new LocalizedException(__('Unable to prepare payment verification. Please try again.'));
            }
            return $id;
        });
    }

    private function key(string $kind, string $scope): string
    {
        return 'awx_' . $kind . '_' . substr(hash('sha256', $scope), 0, 48);
    }

    private function locked(string $key, callable $operation)
    {
        if (!$this->locks->lock($key, 5)) {
            throw new LocalizedException(__('Payment request is in progress. Please try again.'));
        }
        try {
            return $operation();
        } finally {
            $this->locks->unlock($key);
        }
    }
}
