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
 * PolicySavedEvent Class
 *
 * Represent saving a domain policy.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PolicySavedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediversePolicySaved';

    /**
     * Initialize the event.
     *
     * @params string $domain Domain name.
     * @params string $policy Policy value.
     * @params string $reason Optional reason.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(string $domain, string $policy, string $reason, int $userId)
    {
        parent::__construct(
            $userId,
            [
                'domain' => $domain,
                'policy' => $policy,
                'reason' => $reason,
                'user_id' => $userId,
            ]
        );
    }
}
