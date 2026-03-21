<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Federation;

/**
 * DeliveryServiceInterface Interface
 *
 * Define the Delivery Service contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface DeliveryServiceInterface
{
    /**
     * Enqueue delivery jobs.
     *
     * Add delivery queue entries for an outbox item and target inbox URLs.
     *
     * @params int $outboxId Outbox item identifier.
     * @params array $targetInboxUrls Target inbox URLs.
     *
     * @return  int  Number of jobs enqueued.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enqueue(int $outboxId, array $targetInboxUrls): int;

    /**
     * Deliver the next batch of jobs.
     *
     * Claim and deliver a batch of queued jobs.
     *
     * @params int $limit Maximum number of jobs to deliver.
     *
     * @return  int  Number of jobs processed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliverNextBatch(int $limit = 50): int;

    /**
     * Deliver a single job.
     *
     * Execute delivery for the specified job id.
     *
     * @params int $jobId Delivery job identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliverJob(int $jobId): void;
}
