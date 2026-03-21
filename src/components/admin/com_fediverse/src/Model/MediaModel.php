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
 * MediaModel Class
 *
 * Provide database access for media uploads.
 *
 * @since  __DEPLOY_VERSION__
 */
final class MediaModel extends BaseModel
{
    /**
     * Initialize the media model.
     *
     * Store the database dependency for media persistence operations.
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
     * Insert a media record.
     *
     * Persist metadata for an uploaded media file.
     *
     * @params int $localActorId Local actor identifier.
     * @params int $userId Joomla user identifier.
     * @params string $relativePath Relative file path from the site root.
     * @params string $filename Stored file name.
     * @params ?string $originalName Original file name.
     * @params string $mimeType MIME type.
     * @params int $size File size in bytes.
     *
     * @return  int  Inserted media id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertMedia(
        int $localActorId,
        int $userId,
        string $relativePath,
        string $filename,
        ?string $originalName,
        string $mimeType,
        int $size
    ): int {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_media'))
            ->columns([
                $this->db->quoteName('local_actor_id'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('relative_path'),
                $this->db->quoteName('filename'),
                $this->db->quoteName('original_name'),
                $this->db->quoteName('mime_type'),
                $this->db->quoteName('size'),
            ])
            ->values(
                implode(',', [
                    (string) $localActorId,
                    (string) $userId,
                    $this->db->quote($relativePath),
                    $this->db->quote($filename),
                    $originalName !== null ? $this->db->quote($originalName) : 'NULL',
                    $this->db->quote($mimeType),
                    (string) max(0, $size),
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Fetch a media record by id.
     *
     * Load a media row for the provided identifier.
     *
     * @params int $id Media identifier.
     *
     * @return  ?object  Media row or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $id): ?object
    {
        $mid   = $id;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_media'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $mid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }
}
