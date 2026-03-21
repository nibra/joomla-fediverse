<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Actor;

use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * ActorResolverServiceInterface Interface
 *
 * Define the Actor Resolver Service contract.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ActorResolverServiceInterface
{
    /**
     * Ensure a local actor exists for a user.
     *
     * Provision a local actor and active key as needed.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  Actor  Local actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function ensureLocalActorForUserId(int $userId): Actor;

    /**
     * Disable a local actor for a user.
     *
     * Mark the actor disabled and revoke active keys.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disableLocalActorForUserId(int $userId): void;

    /**
     * Rotate the signing key for a local actor by handle.
     *
     * Generate a new key pair and update the active key for the actor.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateSigningKeyForHandle(string $handle): void;

    /**
     * Rotate the signing key for a local actor by user id.
     *
     * Generate a new key pair and update the active key for the actor.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateSigningKeyForUserId(int $userId): void;

    /**
     * Get a stable handle for a user.
     *
     * Return the deterministic handle for a Joomla user id.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  string  Actor handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function stableHandle(int $userId): string;
}
