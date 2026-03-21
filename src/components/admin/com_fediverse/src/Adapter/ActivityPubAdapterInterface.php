<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Adapter;

use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * ActivityPubAdapterInterface Interface
 *
 * Define the Activity Pub Adapter contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ActivityPubAdapterInterface
{
    /**
     * Parse an ActivityPub payload.
     *
     * Convert ActivityStreams JSON into an activity envelope.
     *
     * @params string $json ActivityStreams JSON payload.
     *
     * @return  ActivityEnvelope  Parsed activity envelope.
     * @throws  \RuntimeException  if the JSON is invalid.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function parseActivity(string $json): ActivityEnvelope;

    /**
     * Serialize an activity envelope.
     *
     * Convert a domain activity envelope into ActivityStreams JSON.
     *
     * @params ActivityEnvelope $activity Activity envelope.
     *
     * @return  string  Serialized ActivityStreams JSON.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function serializeActivity(ActivityEnvelope $activity): string;

    /**
     * Fetch a remote actor.
     *
     * Retrieve and parse a remote actor resource.
     *
     * @params string $actorUrl Actor URL.
     *
     * @return  Actor  Remote actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchActor(string $actorUrl): Actor;
}
