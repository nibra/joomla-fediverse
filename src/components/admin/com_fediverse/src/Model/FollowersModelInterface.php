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
 * FollowersModelInterface
 *
 * Contract for followers model implementations.
 *
 * @since  __DEPLOY_VERSION__
 */
interface FollowersModelInterface
{
    /**
     * Get accepted follower inbox URLs.
     *
     * @params int $localActorId Local actor id.
     *
     * @return  array<int,string>  List of inbox URLs.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getAcceptedFollowerInboxUrls(int $localActorId): array;

    /**
     * Get the follower state for a local/remote actor pair.
     *
     * @params int $localActorId Local actor id.
     * @params int $remoteActorId Remote actor id.
     *
     * @return  ?string  Follower state or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getFollowerState(int $localActorId, int $remoteActorId): ?string;

    /**
     * Upsert a follower edge.
     *
     * @params int $localActorId Local actor id.
     * @params int $remoteActorId Remote actor id.
     * @params string $state Follower state.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function upsertFollowerEdge(int $localActorId, int $remoteActorId, string $state): void;

    /**
     * Remove a follower edge.
     *
     * @params int $localActorId Local actor id.
     * @params int $remoteActorId Remote actor id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function removeFollowerEdge(int $localActorId, int $remoteActorId): void;
}
