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
 * ContentPublishedEvent Class
 *
 * Represent publishing federated content for the first time.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ContentPublishedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseContentPublished';

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
