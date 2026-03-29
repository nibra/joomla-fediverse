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
 * ContentDeletedEvent Class
 *
 * Represent deleting federated content.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ContentDeletedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseContentDeleted';

    /**
     * Initialize the event.
     *
     * @params array<string, mixed> $payload Canonical publishing payload.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $payload)
    {
        parent::__construct(
            isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            $payload
        );
    }
}
