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
 * ConfigurationImportedEvent Class
 *
 * Represent importing a portable configuration package.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ConfigurationImportedEvent extends AbstractFediverseDomainEvent
{
    public const NAME = 'onFediverseConfigurationImported';

    /**
     * Initialize the event.
     *
     * @params int $schedulerTasks Imported scheduler task count.
     * @params int $userSettings Imported user settings count.
     * @params int $contentSettings Imported content settings count.
     * @params int $domainPolicies Imported domain policy count.
     * @params int $oauthClients Imported OAuth client count.
     * @params int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        int $schedulerTasks,
        int $userSettings,
        int $contentSettings,
        int $domainPolicies,
        int $oauthClients,
        int $userId
    ) {
        $payload = [
            'scheduler_tasks' => $schedulerTasks,
            'user_settings' => $userSettings,
            'content_settings' => $contentSettings,
            'domain_policies' => $domainPolicies,
            'oauth_clients' => $oauthClients,
            'user_id' => $userId,
        ];

        parent::__construct(
            $userId,
            $payload
        );
    }
}
