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
 * PolicyDeletedEvent Class
 *
 * Represent deleting a domain policy.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PolicyDeletedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediversePolicyDeleted';

    /**
     * Initialize the event.
     *
     * @params string $domain Domain name.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(string $domain, int $userId)
    {
        parent::__construct(
            $userId,
            [
                'domain' => $domain,
                'user_id' => $userId,
            ]
        );
    }
}
