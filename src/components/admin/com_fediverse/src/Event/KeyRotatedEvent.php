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
 * KeyRotatedEvent Class
 *
 * Represent rotating a signing key for a user or handle.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeyRotatedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseKeyRotated';

    /**
     * Initialize the event.
     *
     * @params ?string $handle Rotated actor handle.
     * @params ?int $rotatedUserId Rotated Joomla user id.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(?string $handle, ?int $rotatedUserId, int $userId)
    {
        $byHandle = $handle !== null && trim($handle) !== '';

        parent::__construct(
            $userId,
            [
                'handle' => $handle,
                'rotated_user_id' => $rotatedUserId,
                'user_id' => $userId,
                'by_handle' => $byHandle,
            ]
        );
    }
}
