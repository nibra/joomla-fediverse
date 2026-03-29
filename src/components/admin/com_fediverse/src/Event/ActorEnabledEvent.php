<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Event;

/**
 * ActorEnabledEvent Class
 *
 * Represent enabling one or more local actors.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorEnabledEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseActorEnabled';

    /**
     * Initialize the event.
     *
     * @params array<int, int> $actorIds Enabled actor ids.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $actorIds, int $userId)
    {
        $count = count($actorIds);

        parent::__construct(
            $userId,
            [
                'actor_ids' => $actorIds,
                'count' => $count,
                'ids' => implode(', ', $actorIds),
                'user_id' => $userId,
            ]
        );
    }
}
