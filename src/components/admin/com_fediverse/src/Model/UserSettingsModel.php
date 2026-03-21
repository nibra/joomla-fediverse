<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * UserSettingsModel Class
 *
 * Provide persistence for per-user Fediverse settings.
 *
 * @since  __DEPLOY_VERSION__
 */
final class UserSettingsModel extends BaseModel
{
    /**
     * Initialize the user settings model.
     *
     * @params DatabaseInterface $db Database connection.
     * @params array $config Model configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Check whether federation is enabled for a user.
     *
     * Returns true when no explicit setting exists (opt-in by default).
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  bool  True when federation is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isFederationEnabled(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('federation_enabled'))
            ->from($this->db->quoteName('#__fediverse_user_settings'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $value = $this->db->loadResult();

        // No row means no override – federation is enabled by default.
        if ($value === null) {
            return true;
        }

        return (int) $value === 1;
    }

    /**
     * Save user settings.
     *
     * Insert or update the settings row for the given user.
     *
     * @params int $userId Joomla user identifier.
     * @params bool $federationEnabled Whether federation is enabled.
     * @params string $bio User bio for the actor profile.
     * @params string $website User website for the actor profile.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(int $userId, bool $federationEnabled, string $bio = '', string $website = ''): void
    {
        if ($userId <= 0) {
            return;
        }

        $fedEnabled = $federationEnabled ? 1 : 0;
        $bio        = trim($bio);
        $website    = trim($website);

        $existing = $this->db->createQuery()
            ->select($this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__fediverse_user_settings'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($existing);
        $exists = $this->db->loadResult() !== null;

        if ($exists) {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_user_settings'))
                ->set($this->db->quoteName('federation_enabled') . ' = :fed')
                ->set($this->db->quoteName('bio') . ' = :bio')
                ->set($this->db->quoteName('website') . ' = :website')
                ->where($this->db->quoteName('user_id') . ' = :userId')
                ->bind(':fed', $fedEnabled, ParameterType::INTEGER)
                ->bind(':bio', $bio, ParameterType::STRING)
                ->bind(':website', $website, ParameterType::STRING)
                ->bind(':userId', $userId, ParameterType::INTEGER);
        } else {
            $query = $this->db->createQuery()
                ->insert($this->db->quoteName('#__fediverse_user_settings'))
                ->columns([
                    $this->db->quoteName('user_id'),
                    $this->db->quoteName('federation_enabled'),
                    $this->db->quoteName('bio'),
                    $this->db->quoteName('website'),
                ])
                ->values(':userId, :fed, :bio, :website')
                ->bind(':userId', $userId, ParameterType::INTEGER)
                ->bind(':fed', $fedEnabled, ParameterType::INTEGER)
                ->bind(':bio', $bio, ParameterType::STRING)
                ->bind(':website', $website, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Get user settings row.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  array<string,mixed>  Settings with defaults.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getForUser(int $userId): array
    {
        $defaults = [
            'user_id'            => $userId,
            'federation_enabled' => 1,
            'bio'                => '',
            'website'            => '',
        ];

        if ($userId <= 0) {
            return $defaults;
        }

        $query = $this->db->createQuery()
            ->select(['*'])
            ->from($this->db->quoteName('#__fediverse_user_settings'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadAssoc();

        if ($row === null) {
            return $defaults;
        }

        return array_merge($defaults, $row);
    }
}
