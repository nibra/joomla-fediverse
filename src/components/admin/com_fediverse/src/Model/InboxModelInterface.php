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
 * InboxModelInterface
 *
 * Contract for inbox model implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
interface InboxModelInterface
{
    /**
     * Insert an inbox item.
     *
     * @params ?string $localActorHandle Local actor handle.
     * @params mixed $activity Activity payload.
     * @params string $rawJson Raw JSON string.
     * @params bool $signatureValid Whether the signature is valid.
     *
     * @return  int  Inserted inbox item id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insert(?string $localActorHandle, mixed $activity, string $rawJson, bool $signatureValid): int;

    /**
     * Fetch an inbox item id by activity URI.
     *
     * @params string $activityIdUri Activity id URI.
     *
     * @return  ?int  Inbox item id or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getIdByActivityIdUri(string $activityIdUri): ?int;

    /**
     * Fetch unprocessed inbox items.
     *
     * @params int $limit Maximum number of items.
     *
     * @return  array<int,object>  Unprocessed inbox items.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchUnprocessed(int $limit): array;

    /**
     * Get an inbox item by id.
     *
     * @params int $id Inbox item id.
     *
     * @return  ?object  Inbox item row or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $id): ?object;

    /**
     * Mark an inbox item as processed.
     *
     * @params int $id Inbox item id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markProcessed(int $id): void;

    /**
     * Mark an inbox item as failed.
     *
     * @params int $id Inbox item id.
     * @params string $error Error message.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailed(int $id, string $error): void;

    /**
     * Mark an inbox item as ignored.
     *
     * @params int $id Inbox item id.
     * @params ?string $reason Ignore reason.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markIgnored(int $id, ?string $reason = null): void;

    /**
     * Delete inbox items older than the given number of days.
     *
     * @params int $days Retention period in days.
     *
     * @return  int  Number of rows deleted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteOlderThan(int $days): int;

    /**
     * Get a list of inbox items.
     *
     * @params string $status Status filter.
     * @params int $limit Maximum number of items.
     * @params int $offset Result offset.
     *
     * @return  array<int,array<string,mixed>>  List of inbox items.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(string $status = '', int $limit = 50, int $offset = 0): array;
}
