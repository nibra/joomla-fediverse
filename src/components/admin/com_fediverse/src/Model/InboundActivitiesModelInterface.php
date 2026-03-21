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
 * InboundActivitiesModelInterface
 *
 * Contract for inbound activities model implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
interface InboundActivitiesModelInterface
{
    /**
     * Insert an inbound activity record.
     *
     * @params int $inboxId Inbox item id.
     * @params ?int $localActorId Local actor id.
     * @params ?int $remoteActorId Remote actor id.
     * @params ?string $activityIdUri Activity id URI.
     * @params string $activityType Activity type.
     * @params ?string $objectUri Target object URI.
     * @params ?string $objectId Target object id.
     * @params string $rawJson Raw activity JSON.
     *
     * @return  int  Inserted inbound activity id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertActivity(
        int $inboxId,
        ?int $localActorId,
        ?int $remoteActorId,
        ?string $activityIdUri,
        string $activityType,
        ?string $objectUri,
        ?string $objectId,
        string $rawJson
    ): int;

    /**
     * Fetch an inbound activity id by activity URI.
     *
     * @params string $activityIdUri Activity id URI.
     *
     * @return  ?int  Inbound activity id or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getIdByActivityIdUri(string $activityIdUri): ?int;
}
