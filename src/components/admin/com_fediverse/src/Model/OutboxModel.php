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
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;

/**
 * OutboxModel Class
 *
 * Provide database access for Outbox data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class OutboxModel extends BaseModel implements OutboxModelInterface
{
    /**
     * Initialize the outbox model.
     *
     * Store the database dependency for outbox persistence operations.
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
     * Insert an outbox envelope.
     *
     * Persist a local actor activity into the outbox.
     *
     * @params int $localActorId Local actor identifier.
     * @params ActivityEnvelope $activity Activity envelope.
     *
     * @return  int  Inserted outbox item id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertEnvelope(int $localActorId, ActivityEnvelope $activity): int
    {
        $row = self::buildRowFromEnvelope($localActorId, $activity);

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_outbox'))
            ->columns([
                $this->db->quoteName('local_actor_id'),
                $this->db->quoteName('activity_id_uri'),
                $this->db->quoteName('type'),
                $this->db->quoteName('raw_json'),
                $this->db->quoteName('state'),
            ])
            ->values(
                implode(',', [
                    (string) ((int) $row['local_actor_id']),
                    $this->db->quote($row['activity_id_uri']),
                    $this->db->quote($row['type']),
                    $this->db->quote($row['raw_json']),
                    $this->db->quote($row['state']),
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Build an outbox row from an activity envelope.
     *
     * Convert an activity envelope into a row payload for persistence.
     *
     * @params int $localActorId Local actor identifier.
     * @params ActivityEnvelope $activity Activity envelope.
     *
     * @return  array{local_actor_id:int,activity_id_uri:string,type:string,raw_json:string,state:string}  Outbox row.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function buildRowFromEnvelope(int $localActorId, ActivityEnvelope $activity): array
    {
        return [
            'local_actor_id'  => $localActorId,
            'activity_id_uri' => $activity->idUri,
            'type'            => $activity->type,
            'raw_json'        => $activity->toJsonString(),
            'state'           => 'queued',
        ];
    }

    /**
     * Fetch the outbox payload.
     *
     * Load the raw JSON payload for a stored outbox item.
     *
     * @params int $outboxId Outbox item identifier.
     *
     * @return  string  Raw activity JSON or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayload(int $outboxId): string
    {
        $id    = $outboxId;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('raw_json'))
            ->from($this->db->quoteName('#__fediverse_outbox'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $payload = $this->db->loadResult();

        return is_string($payload) ? $payload : '';
    }

    /**
     * Count outbox items for an actor.
     *
     * Return the number of outbox entries for a local actor.
     *
     * @params int $localActorId Local actor identifier.
     *
     * @return  int  Total outbox entries.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function countForActor(int $localActorId): int
    {
        $id    = $localActorId;
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__fediverse_outbox'))
            ->where($this->db->quoteName('local_actor_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $count = $this->db->loadResult();

        return (int) ($count ?? 0);
    }

    /**
     * Fetch outbox payloads for an actor.
     *
     * Return a page of decoded activity payloads in reverse chronological order.
     *
     * @params int $localActorId Local actor identifier.
     * @params int $limit Maximum number of items to return.
     * @params int $offset Result offset for paging.
     *
     * @return  array<int,array<string,mixed>>  Activity payloads.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayloadsForActor(int $localActorId, int $limit, int $offset = 0): array
    {
        $id    = $localActorId;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('raw_json'))
            ->from($this->db->quoteName('#__fediverse_outbox'))
            ->where($this->db->quoteName('local_actor_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' DESC')
            ->setLimit($limit, $offset);

        $this->db->setQuery($query);
        $rows = $this->db->loadColumn();

        $items = [];
        foreach ($rows as $row) {
            if (!is_string($row) || trim($row) === '') {
                continue;
            }

            $decoded = json_decode($row, true);
            if (!is_array($decoded)) {
                continue;
            }

            $items[] = $decoded;
        }

        return $items;
    }

    /**
     * Update the outbox item state.
     *
     * Persist a new state value for an outbox item.
     *
     * @params int $outboxId Outbox item identifier.
     * @params string $state Outbox state value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markState(int $outboxId, string $state): void
    {
        $id = $outboxId;
        $st = $state;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_outbox'))
            ->set($this->db->quoteName('state') . ' = :st')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':st', $st, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Delete queued Update activities for a given object URI.
     *
     * Purge superseded queued Update rows for an actor before inserting a new one.
     *
     * @params int $localActorId Local actor identifier.
     * @params string $objectUri Object URI to match.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteQueuedUpdatesForObject(int $localActorId, string $objectUri): void
    {
        try {
            $actorId = $localActorId;
            $type    = 'Update';
            $state   = 'queued';
            $like    = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $objectUri) . '%';

            $query = $this->db->createQuery()
                ->delete($this->db->quoteName('#__fediverse_outbox'))
                ->where($this->db->quoteName('local_actor_id') . ' = :actorId')
                ->where($this->db->quoteName('type') . ' = :type')
                ->where($this->db->quoteName('state') . ' = :state')
                ->where($this->db->quoteName('raw_json') . ' LIKE :like')
                ->bind(':actorId', $actorId, ParameterType::INTEGER)
                ->bind(':type', $type, ParameterType::STRING)
                ->bind(':state', $state, ParameterType::STRING)
                ->bind(':like', $like, ParameterType::STRING);

            $this->db->setQuery($query);
            $this->db->execute();
        } catch (\Throwable) {
            // Cleanup failure is non-fatal — the new envelope will still be inserted.
        }
    }

    /**
     * Delete delivered outbox rows older than the given number of days.
     *
     * @params int $days Retention period in days.
     *
     * @return  int  Number of rows deleted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteOlderThan(int $days): int
    {
        $cutoff   = (new \DateTimeImmutable())->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');
        $statuses = ['delivered'];

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_outbox'))
            ->where($this->db->quoteName('created_at') . ' < :cutoff')
            ->where($this->db->quoteName('state') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $statuses)) . ')')
            ->bind(':cutoff', $cutoff, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }
}
