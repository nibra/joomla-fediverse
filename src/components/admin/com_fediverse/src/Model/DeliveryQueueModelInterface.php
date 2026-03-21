<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

/**
 * DeliveryQueueModelInterface
 *
 * Contract for delivery queue model implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
interface DeliveryQueueModelInterface
{
    /**
     * Bulk-insert delivery queue entries.
     *
     * @params int $outboxId Outbox item id.
     * @params array<int,string> $targetInboxUrls Target inbox URLs.
     *
     * @return  int  Number of rows inserted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function bulkInsert(int $outboxId, array $targetInboxUrls): int;

    /**
     * Claim due delivery jobs.
     *
     * @params int $limit Maximum number of jobs.
     *
     * @return  array<int,mixed>  Claimed delivery jobs.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function claimDue(int $limit): array;

    /**
     * Get a delivery job by id.
     *
     * @params int $jobId Job id.
     *
     * @return  mixed  Job row or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $jobId): mixed;

    /**
     * Mark a delivery job as successful.
     *
     * @params int $jobId Job id.
     * @params int $status HTTP status code.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markSuccess(int $jobId, int $status): void;

    /**
     * Mark a delivery job as failed and schedule a retry.
     *
     * @params int $jobId Job id.
     * @params int $status HTTP status code.
     * @params string $body Response body.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailureAndScheduleRetry(int $jobId, int $status, string $body): void;

    /**
     * Delete delivered jobs older than the given number of days.
     *
     * @params int $days Retention period in days.
     *
     * @return  int  Number of rows deleted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteOlderThan(int $days): int;

    /**
     * Get a list of delivery queue items.
     *
     * @params string $state State filter.
     * @params int $limit Maximum number of items.
     * @params int $offset Result offset.
     *
     * @return  array<int,array<string,mixed>>  List of delivery queue items.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(string $state = '', int $limit = 50, int $offset = 0): array;
}
