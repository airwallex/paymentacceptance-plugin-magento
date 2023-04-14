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
namespace Airwallex\Payments\Model\Webhook;

use Airwallex\Payments\Exception\WebhookException;
use Airwallex\Payments\Helper\Configuration;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use stdClass;

class Webhook
{
    private const HASH_ALGORITHM = 'sha256';

    /**
     * @var Refund
     */
    private Refund $refund;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var Capture
     */
    private Capture $capture;

    /**
     * @var Cancel
     */
    private Cancel $cancel;

    /**
     * Webhook constructor.
     *
     * @param Configuration $configuration
     * @param Refund $refund
     * @param Capture $capture
     * @param Cancel $cancel
     */
    public function __construct(Configuration $configuration, Refund $refund, Capture $capture, Cancel $cancel)
    {
        $this->refund = $refund;
        $this->configuration = $configuration;
        $this->capture = $capture;
        $this->cancel = $cancel;
    }

    /**
     * @param Http $request
     *
     * @return void
     * @throws WebhookException
     */
    public function checkChecksum(Http $request): void
    {
        $signature = $request->getHeader('x-signature');
        $data = $request->getHeader('x-timestamp') . $request->getContent();

        if (hash_hmac(self::HASH_ALGORITHM, $data, $this->configuration->getWebhookSecretKey()) !== $signature) {
            throw new WebhookException(__('failed to verify the signature'));
        }
    }

    /**
     * @param string $type
     * @param stdClass $data
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WebhookException
     */
    public function dispatch(string $type, stdClass $data): void
    {
        if ($type === Refund::WEBHOOK_SUCCESS_NAME) {
            $this->refund->execute($data);
        }

        if (in_array($type, Capture::WEBHOOK_NAMES)) {
            $this->capture->execute($data);
        }

        if ($type === Cancel::WEBHOOK_NAME) {
            $this->cancel->execute($data);
        }
    }
}
