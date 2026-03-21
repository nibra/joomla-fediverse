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
 * UsersModel Class
 *
 * Provide database access for Users data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class UsersModel extends BaseModel
{
    /**
     * Initialize the users model.
     *
     * Store the database dependency for user lookup operations.
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
     * Fetch a user by id.
     *
     * Load a minimal user record containing id and username.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  ?object  User record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $userId): ?object
    {
        $id    = $userId;
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('username'),
            ])
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Find a user id by username.
     *
     * Resolve the Joomla user identifier for a given username.
     *
     * @params string $username Username to resolve.
     *
     * @return  ?int  User id or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function findUserIdByUsername(string $username): ?int
    {
        $u     = $username;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('username') . ' = :u')
            ->bind(':u', $u, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $id = $this->db->loadResult();

        return $id !== null ? (int) $id : null;
    }
}
