<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Access;

/**
 * PermissionService Class
 *
 * Centralize Fediverse ACL checks for controller actions.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PermissionService
{
    /**
     * Enforce strict action-level ACL checks without global fallback actions.
     *
     * @var bool
     *
     * @since  __DEPLOY_VERSION__
     */
    private bool $strictActionAcl;

    /**
     * Class constructor.
     *
     * @params bool $strictActionAcl True to disable fallback ACL checks.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(bool $strictActionAcl = false)
    {
        $this->strictActionAcl = $strictActionAcl;
    }

    /**
     * Check whether the user can manage inbox moderation/actions.
     *
     * @params object $user Joomla user identity.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function canManageInbox(object $user): bool
    {
        return $this->isAllowed($user, 'fediverse.manage.inbox');
    }

    /**
     * Check whether the user can manage policy records.
     *
     * @params object $user Joomla user identity.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function canManagePolicies(object $user): bool
    {
        return $this->isAllowed($user, 'fediverse.manage.policies');
    }

    /**
     * Check whether the user can rotate actor signing keys.
     *
     * @params object $user Joomla user identity.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function canRotateKeys(object $user): bool
    {
        return $this->isAllowed($user, 'fediverse.manage.keys');
    }

    /**
     * Check whether the user can manage OAuth clients.
     *
     * @params object $user Joomla user identity.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function canManageOAuthClients(object $user): bool
    {
        return $this->isAllowed($user, 'fediverse.manage.oauthclients');
    }

    /**
     * Check whether the user can manage webhook endpoints.
     *
     * @params object $user Joomla user identity.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function canManageWebhooks(object $user): bool
    {
        return $this->isAllowed($user, 'fediverse.manage.webhooks');
    }

    /**
     * Check action-specific permission with global Fediverse fallbacks.
     *
     * @params object $user Joomla user identity.
     * @params string $action Fediverse action name.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isAllowed(object $user, string $action): bool
    {
        if (!method_exists($user, 'authorise')) {
            return false;
        }

        if ($user->authorise($action, 'com_fediverse')) {
            return true;
        }

        if ($this->strictActionAcl) {
            return false;
        }

        return $user->authorise('core.manage', 'com_fediverse')
            || $user->authorise('core.admin', 'com_fediverse')
            || $user->authorise('core.options', 'com_fediverse');
    }
}
