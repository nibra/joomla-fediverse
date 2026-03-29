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
 * InboxRepliesModeratedEvent Class
 *
 * Represent moderation changes for inbound replies.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboxRepliesModeratedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseInboxRepliesModerated';

    /**
     * Initialize the event.
     *
     * @params array<int, int> $inboxIds Inbox ids.
     * @params int $count Number of updated rows.
     * @params string $state Moderation state.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $inboxIds, int $count, string $state, int $userId)
    {
        parent::__construct(
            $userId,
            [
                'inbox_ids' => $inboxIds,
                'count' => $count,
                'ids' => implode(', ', $inboxIds),
                'state' => $state,
                'user_id' => $userId,
            ]
        );
    }
}
