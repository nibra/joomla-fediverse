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
 * ContentSettingsModel Class
 *
 * Provide persistence for per-content Fediverse opt-in/out settings.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ContentSettingsModel extends BaseModel
{
    /**
     * Initialize the content settings model.
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
     * Check whether federation is enabled for a content item.
     *
     * Returns true when no explicit setting exists (opt-in by default).
     *
     * @params string $context Joomla content context (e.g. com_content.article).
     * @params int $itemId Content item identifier.
     *
     * @return  bool  True when federation is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isFederationEnabled(string $context, int $itemId): bool
    {
        if ($context === '' || $itemId <= 0) {
            return false;
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('federate'))
            ->from($this->db->quoteName('#__fediverse_content_settings'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('item_id') . ' = :itemId')
            ->bind(':context', $context, ParameterType::STRING)
            ->bind(':itemId', $itemId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $value = $this->db->loadResult();

        if ($value === null) {
            return true;
        }

        return (int) $value === 1;
    }

    /**
     * Save federation opt-in/out for a content item.
     *
     * @params string $context Joomla content context.
     * @params int $itemId Content item identifier.
     * @params bool $federate Whether to federate this item.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(string $context, int $itemId, bool $federate): void
    {
        if ($context === '' || $itemId <= 0) {
            return;
        }

        $federateInt = $federate ? 1 : 0;

        // Use INSERT ... ON DUPLICATE KEY UPDATE pattern via a manual query.
        $sql = 'INSERT INTO ' . $this->db->quoteName('#__fediverse_content_settings')
            . ' (' . $this->db->quoteName('context') . ', ' . $this->db->quoteName('item_id') . ', ' . $this->db->quoteName('federate') . ')'
            . ' VALUES (:context, :itemId, :federate)'
            . ' ON DUPLICATE KEY UPDATE ' . $this->db->quoteName('federate') . ' = VALUES(' . $this->db->quoteName('federate') . ')';

        $query = $this->db->createQuery();
        $query->setQuery($sql)
            ->bind(':context', $context, ParameterType::STRING)
            ->bind(':itemId', $itemId, ParameterType::INTEGER)
            ->bind(':federate', $federateInt, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
