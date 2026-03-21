<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;

/**
 * OutboxModelInterface
 *
 * Contract for outbox model implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
interface OutboxModelInterface
{
    /**
     * Insert an outbox envelope.
     *
     * @params int $localActorId Local actor id.
     * @params ActivityEnvelope $activity Activity envelope.
     *
     * @return  int  Inserted outbox item id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertEnvelope(int $localActorId, ActivityEnvelope $activity): int;

    /**
     * Fetch the outbox payload.
     *
     * @params int $outboxId Outbox item id.
     *
     * @return  string  Raw activity JSON.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayload(int $outboxId): string;

    /**
     * Count outbox items for an actor.
     *
     * @params int $localActorId Local actor id.
     *
     * @return  int  Total outbox entries.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function countForActor(int $localActorId): int;

    /**
     * Fetch outbox payloads for an actor.
     *
     * @params int $localActorId Local actor id.
     * @params int $limit Maximum number of items.
     * @params int $offset Result offset.
     *
     * @return  array<int,array<string,mixed>>  Activity payloads.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayloadsForActor(int $localActorId, int $limit, int $offset = 0): array;

    /**
     * Update the outbox item state.
     *
     * @params int $outboxId Outbox item id.
     * @params string $state Outbox state value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markState(int $outboxId, string $state): void;

    /**
     * Delete queued Update activities for a given object URI.
     *
     * @params int $localActorId Local actor id.
     * @params string $objectUri Object URI.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteQueuedUpdatesForObject(int $localActorId, string $objectUri): void;

    /**
     * Delete delivered outbox rows older than the given number of days.
     *
     * @params int $days Retention period in days.
     *
     * @return  int  Number of rows deleted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteOlderThan(int $days): int;
}
