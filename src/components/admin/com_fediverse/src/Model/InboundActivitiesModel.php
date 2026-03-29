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
 * InboundActivitiesModel Class
 *
 * Provide database access for inbound activities.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboundActivitiesModel extends BaseModel implements InboundActivitiesModelInterface
{
    /**
     * Initialize the inbound activities model.
     *
     * Store the database dependency for inbound activity persistence operations.
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
     * Insert an inbound activity record.
     *
     * Persist a normalized inbound activity for later querying.
     *
     * @params int $inboxId Inbox item id.
     * @params ?int $localActorId Local actor id.
     * @params ?int $remoteActorId Remote actor id.
     * @params ?string $activityIdUri Activity id URI.
     * @params string $activityType Activity type.
     * @params ?string $objectUri Target object URI.
     * @params ?string $objectId Target object id segment.
     * @params string $rawJson Raw activity JSON.
     *
     * @return  int  Inserted inbound activity id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertActivity(
        int $inboxId,
        ?int $localActorId,
        ?int $remoteActorId,
        ?string $activityIdUri,
        string $activityType,
        ?string $objectUri,
        ?string $objectId,
        string $rawJson
    ): int {
        $moderationState = strcasecmp($activityType, 'Create') === 0 ? 'pending' : 'approved';

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_inbound_activities'))
            ->columns([
                $this->db->quoteName('inbox_id'),
                $this->db->quoteName('local_actor_id'),
                $this->db->quoteName('remote_actor_id'),
                $this->db->quoteName('activity_id_uri'),
                $this->db->quoteName('activity_type'),
                $this->db->quoteName('object_uri'),
                $this->db->quoteName('object_id'),
                $this->db->quoteName('raw_json'),
                $this->db->quoteName('moderation_state'),
            ])
            ->values(
                implode(',', [
                    (string) $inboxId,
                    $localActorId === null ? 'NULL' : (string) $localActorId,
                    $remoteActorId === null ? 'NULL' : (string) $remoteActorId,
                    $activityIdUri === null ? 'NULL' : $this->db->quote($activityIdUri),
                    $this->db->quote($activityType),
                    $objectUri === null ? 'NULL' : $this->db->quote($objectUri),
                    $objectId === null ? 'NULL' : $this->db->quote($objectId),
                    $this->db->quote($rawJson),
                    $this->db->quote($moderationState),
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Fetch an inbound activity id by activity URI.
     *
     * Look up an existing inbound activity using the activity id URI.
     *
     * @params string $activityIdUri Activity id URI.
     *
     * @return  ?int  Inbound activity id or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getIdByActivityIdUri(string $activityIdUri): ?int
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_inbound_activities'))
            ->where($this->db->quoteName('activity_id_uri') . ' = :activityId')
            ->bind(':activityId', $activityIdUri, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $id = $this->db->loadResult();

        return $id !== null ? (int) $id : null;
    }
}
