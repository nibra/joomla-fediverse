<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_profile
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Profile\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\UsersModel;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;

/**
 * ProfileDataProvider Class
 *
 * Resolve module profile data from Joomla user and actor configuration.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ProfileDataProvider
{
    /**
     * Get profile data.
     *
     * Return the requested data.
     *
     * @params Registry $params Module parameters.
     *
     * @return  array<string,mixed>  Profile data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getProfileData(Registry $params): array
    {
        $source = (string) $params->get('source', 'current');
        $handle = '';
        $userId = null;
        $username = '';
        $displayName = '';

        if ($source === 'handle') {
            $handle = trim((string) $params->get('handle', ''));
        } else {
            $user = null;

            if ($source === 'user_id') {
                $userId = (int) $params->get('user_id', 0);
                if ($userId > 0) {
                    $user = Factory::getUser($userId);
                }
            } elseif ($source === 'username') {
                $name = trim((string) $params->get('username', ''));
                if ($name !== '') {
                    $userId = self::resolveUserIdByUsername($name);
                    if ($userId !== null && $userId > 0) {
                        $user = Factory::getUser($userId);
                    }
                }
            } else {
                $user = Factory::getApplication()->getIdentity();
            }

            $resolvedId = $user !== null ? (int) ($user->id ?? 0) : 0;
            if ($user === null || $resolvedId <= 0) {
                return [
                    'available' => false,
                    'reason'    => 'user_not_found',
                ];
            }

            $userId = $resolvedId;
            $username = (string) ($user->username ?? '');
            $displayName = (string) ($user->name ?? $username);

            $config = new FediverseConfig();
            $handle = $config->formatActorHandle($userId, $username);
        }

        $handle = trim($handle);
        if ($handle === '') {
            return [
                'available' => false,
                'reason'    => 'handle_missing',
            ];
        }

        $config = new FediverseConfig();
        $baseUrlProvider = new JoomlaBaseUrlProvider($config);
        $baseUrl = $baseUrlProvider->getBaseUrl();
        $mapper = new JoomlaUserActorMapper($config);
        $actorUrl = $mapper->actorUri($baseUrl, $handle);
        $host = Uri::getInstance($baseUrl)->getHost();

        $acct = $host !== '' ? 'acct:' . $handle . '@' . $host : '';
        $displayHandle = $host !== '' ? '@' . $handle . '@' . $host : '@' . $handle;

        return [
            'available'      => true,
            'handle'         => $handle,
            'display_handle' => $displayHandle,
            'acct'           => $acct,
            'actor_url'      => $actorUrl,
            'user_id'        => $userId,
            'username'       => $username,
            'display_name'   => $displayName !== '' ? $displayName : $username,
        ];
    }

    /**
     * Resolve a Joomla user id by username.
     *
     * @params string $username Joomla username.
     *
     * @return  ?int  User id or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveUserIdByUsername(string $username): ?int
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $usersModel = new UsersModel($db);

        return $usersModel->findUserIdByUsername($username);
    }
}
