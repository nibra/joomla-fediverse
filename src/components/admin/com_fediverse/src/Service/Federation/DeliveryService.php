<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Federation;

use NX\Component\Fediverse\Administrator\Model\DeliveryQueueModelInterface;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;

/**
 * DeliveryService Class
 *
 * Provide Delivery services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DeliveryService
    implements DeliveryServiceInterface
{
    /**
     * Initialize the delivery service.
     *
     * Store dependencies required for delivery queue processing.
     *
     * @params DeliveryQueueModel $queueModel Delivery queue model.
     * @params KeysModel $keysModel Keys model.
     * @params HttpSignaturServiceInterface $signature Signature service.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private DeliveryQueueModelInterface $queueModel,
        private KeysModel $keysModel,
        private HttpSignaturServiceInterface $signature,
    ) {
    }

    /**
     * Enqueue delivery jobs for an outbox item.
     *
     * Add queue entries for the specified target inbox URLs.
     *
     * @params int $outboxId Outbox item identifier.
     * @params string[] $targetInboxUrls Target inbox URLs.
     *
     * @return  int  Number of jobs enqueued.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enqueue(int $outboxId, array $targetInboxUrls): int
    {
        return $this->queueModel->bulkInsert($outboxId, $targetInboxUrls);
    }

    /**
     * Deliver the next batch of jobs.
     *
     * Claim and attempt delivery for the next due jobs.
     *
     * @params int $limit Maximum number of jobs to deliver.
     *
     * @return  int  Number of jobs processed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliverNextBatch(int $limit = 50): int
    {
        $jobs  = $this->queueModel->claimDue($limit);
        $count = 0;

        foreach ($jobs as $job) {
            $this->deliverJob((int) $job->id);
            $count++;
        }

        return $count;
    }

    /**
     * Deliver a single job.
     *
     * Attempt delivery and update job state based on outcome.
     *
     * @params int $jobId Delivery job identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliverJob(int $jobId): void
    {
        $job = $this->queueModel->getById($jobId);
        if ($job === null) {
            return;
        }

        $targetInboxUrl = (string) ($job->targetInboxUrl ?? '');
        $payload        = (string) ($job->payload ?? '');
        $keyId          = $job->localActorKeyId ?? null;

        // Without an active key we cannot sign requests; schedule retry.
        if (!is_string($keyId) || $keyId === '') {
            $this->queueModel->markFailureAndScheduleRetry($jobId, 500, 'Missing signing key');

            return;
        }

        $res = $this->signature->signedPostRequest($targetInboxUrl, $payload, $keyId);

        // HttpSignaturService is currently a stub; be conservative.
        if (!is_array($res) || !isset($res['status'])) {
            $this->queueModel->markFailureAndScheduleRetry(
                $jobId,
                500,
                'Signing/HTTP client not implemented'
            );

            return;
        }

        $status = (int) $res['status'];
        $body   = (string) ($res['body'] ?? '');

        if ($status >= 200 && $status < 300) {
            $this->queueModel->markSuccess($jobId, $status);

            return;
        }

        if (in_array($status, [401, 403], true)) {
            $fallbackKeyId = null;
            if (isset($job->localActorId)) {
                $fallbackKeyId = $this->keysModel->getLatestRotatedKeyId((int) $job->localActorId);
            }

            if (is_string($fallbackKeyId) && $fallbackKeyId !== '' && $fallbackKeyId !== $keyId) {
                $fallback = $this->signature->signedPostRequest($targetInboxUrl, $payload, $fallbackKeyId);
                if (is_array($fallback) && isset($fallback['status'])) {
                    $fallbackStatus = (int) $fallback['status'];
                    if ($fallbackStatus >= 200 && $fallbackStatus < 300) {
                        $this->queueModel->markSuccess($jobId, $fallbackStatus);

                        return;
                    }

                    $status = $fallbackStatus;
                    $body   = (string) ($fallback['body'] ?? $body);
                }
            }
        }

        $this->queueModel->markFailureAndScheduleRetry($jobId, $status, $body);
    }
}
