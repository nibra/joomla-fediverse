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
 * ActorDisabledEvent Class
 *
 * Represent disabling one or more local actors.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorDisabledEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseActorDisabled';

    /**
     * Initialize the event.
     *
     * @params array<int, int> $actorIds Disabled actor ids.
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
