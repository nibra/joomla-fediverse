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
 * InboxModel Class
 *
 * Provide database access for Inbox data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboxModel extends BaseModel implements InboxModelInterface
{
    /**
     * Initialize the inbox model.
     *
     * Store the database dependency for inbox persistence operations.
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
     * Insert an inbox item.
     *
     * Persist raw activity data along with inferred metadata.
     *
     * @params ?string $localActorHandle Local actor handle or null for shared inbox.
     * @params mixed $activity Parsed activity object or envelope.
     * @params string $rawJson Raw activity JSON.
     * @params bool $signatureValid Signature verification result.
     *
     * @return  int  Inserted inbox item id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insert(?string $localActorHandle, mixed $activity, string $rawJson, bool $signatureValid): int
    {
        [$activityIdUri, $type] = self::inferActivityMeta($activity, $rawJson);

        if ($activityIdUri !== null) {
            $existingId = $this->getIdByActivityIdUri($activityIdUri);
            if ($existingId !== null) {
                return $existingId;
            }
        }

        $localActorId = null;
        if ($localActorHandle !== null && trim($localActorHandle) !== '') {
            $handle = trim($localActorHandle);

            $query = $this->db->createQuery()
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__fediverse_actors'))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('local'))
                ->where($this->db->quoteName('handle') . ' = :handle')
                ->bind(':handle', $handle, ParameterType::STRING)
                ->setLimit(1);

            $this->db->setQuery($query);
            $id           = $this->db->loadResult();
            $localActorId = $id !== null ? (int) $id : null;
        }

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_inbox'))
            ->columns([
                $this->db->quoteName('local_actor_id'),
                $this->db->quoteName('activity_id_uri'),
                $this->db->quoteName('type'),
                $this->db->quoteName('raw_json'),
                $this->db->quoteName('signature_valid'),
                $this->db->quoteName('status'),
            ])
            ->values(
                implode(',', [
                    $localActorId === null ? 'NULL' : (string) $localActorId,
                    $activityIdUri === null ? 'NULL' : $this->db->quote($activityIdUri),
                    $this->db->quote($type),
                    $this->db->quote($rawJson),
                    (string) ((int) $signatureValid),
                    $this->db->quote('received'),
                ])
            );

        try {
            $this->db->setQuery($query);
            $this->db->execute();
        } catch (\Throwable $exception) {
            if ($activityIdUri !== null && $this->isDuplicateActivityInsert($exception)) {
                $existingId = $this->getIdByActivityIdUri($activityIdUri);
                if ($existingId !== null) {
                    return $existingId;
                }
            }

            throw $exception;
        }

        return (int) $this->db->insertid();
    }

    /**
     * Fetch an inbox id by activity URI.
     *
     * Look up an existing inbox record using the activity id URI.
     *
     * @params string $activityIdUri Activity id URI.
     *
     * @return  ?int  Inbox id or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getIdByActivityIdUri(string $activityIdUri): ?int
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_inbox'))
            ->where($this->db->quoteName('activity_id_uri') . ' = :activityId')
            ->bind(':activityId', $activityIdUri, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $id = $this->db->loadResult();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Check if an insert failed due to a duplicate activity id.
     *
     * Inspect the exception message for the inbox activity id unique key.
     *
     * @params \Throwable $exception Insert exception.
     *
     * @return  bool  True when the failure is a duplicate activity id.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isDuplicateActivityInsert(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate entry')
            && str_contains($message, 'uniq_fed_inbox_activityid');
    }

    /**
     * Infer activity metadata from payloads.
     *
     * Derive the activity id URI and type from the parsed activity or raw JSON.
     *
     * @params mixed $activity Parsed activity object or envelope.
     * @params string $rawJson Raw activity JSON.
     *
     * @return  array{0:?string,1:string}  Activity id URI and type.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function inferActivityMeta(mixed $activity, string $rawJson): array
    {
        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $activityIdUri = null;

        // Common object shapes
        if (is_object($activity)) {
            if (isset($activity->idUri) && is_string($activity->idUri) && $activity->idUri !== '') {
                $activityIdUri = $activity->idUri;
            } elseif (isset($activity->activityIdUri) && is_string(
                    $activity->activityIdUri
                ) && $activity->activityIdUri !== '') {
                $activityIdUri = $activity->activityIdUri;
            } elseif (method_exists($activity, 'getActivityIdUri')) {
                $v = $activity->getActivityIdUri();
                if (is_string($v) && $v !== '') {
                    $activityIdUri = $v;
                }
            }
        }

        if ($activityIdUri === null && isset($decoded['id']) && is_string($decoded['id']) && $decoded['id'] !== '') {
            $activityIdUri = $decoded['id'];
        }

        $type = 'Unknown';

        if (is_object($activity)) {
            if (isset($activity->type) && is_string($activity->type) && $activity->type !== '') {
                $type = $activity->type;
            } elseif (isset($activity->type) && is_scalar($activity->type) && (string) $activity->type !== '') {
                $type = (string) $activity->type;
            } elseif (method_exists($activity, 'getType')) {
                $v = $activity->getType();
                if (is_string($v) && $v !== '') {
                    $type = $v;
                }
            }
        }

        if ($type === 'Unknown' && isset($decoded['type']) && is_string($decoded['type']) && $decoded['type'] !== '') {
            $type = $decoded['type'];
        }

        return [$activityIdUri, $type];
    }

    /**
     * Fetch unprocessed inbox items.
     *
     * Load pending inbox ids ordered by receive time.
     *
     * @params int $limit Maximum number of ids to fetch.
     *
     * @return  array<int, object>  List of objects with at least an id field.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchUnprocessed(int $limit): array
    {
        $limit = max(1, $limit);

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_inbox'))
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('received'))
            ->order($this->db->quoteName('received_at') . ' ASC')
            ->setLimit($limit);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Fetch an inbox item by id.
     *
     * Load a single inbox row for the provided identifier.
     *
     * @params int $id Inbox item identifier.
     *
     * @return  ?object  Inbox row or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $id): ?object
    {
        $iid   = $id;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_inbox'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $iid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Mark an inbox item as processed.
     *
     * Update the inbox status and processing timestamp.
     *
     * @params int $id Inbox item identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markProcessed(int $id): void
    {
        $iid   = $id;
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_inbox'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('processed'))
            ->set($this->db->quoteName('processed_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $iid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Mark an inbox item as failed.
     *
     * Update the inbox status, processing timestamp, and error details.
     *
     * @params int $id Inbox item identifier.
     * @params string $error Failure description.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailed(int $id, string $error): void
    {
        $iid = $id;
        $err = mb_substr($error, 0, 4000);

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_inbox'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('failed'))
            ->set($this->db->quoteName('processed_at') . ' = NOW()')
            ->set($this->db->quoteName('error') . ' = :err')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $iid, ParameterType::INTEGER)
            ->bind(':err', $err, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Mark an inbox item as ignored.
     *
     * Update the inbox status, processing timestamp, and optional reason.
     *
     * @params int $id Inbox item identifier.
     * @params ?string $reason Optional ignore reason.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    /**
     * Delete processed/ignored inbox rows older than the given number of days.
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
        $statuses = ['processed', 'ignored', 'failed'];

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_inbox'))
            ->where($this->db->quoteName('received_at') . ' < :cutoff')
            ->where($this->db->quoteName('status') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $statuses)) . ')')
            ->bind(':cutoff', $cutoff, \Joomla\Database\ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }

    public function markIgnored(int $id, ?string $reason = null): void
    {
        $iid = $id;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_inbox'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('ignored'))
            ->set($this->db->quoteName('processed_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $iid, ParameterType::INTEGER);

        if ($reason !== null && $reason !== '') {
            $err = mb_substr($reason, 0, 4000);
            $query->set($this->db->quoteName('error') . ' = :err')
                ->bind(':err', $err, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Fetch a paginated list of inbox items.
     *
     * @params string $status  Optional status filter ('pending','processed','failed','ignored').
     * @params int    $limit   Maximum number of rows.
     * @params int    $offset  Row offset.
     * @params string $search  Optional search term.
     *
     * @return  array<int, array<string, mixed>>  List of inbox rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(
        string $status = '',
        int $limit = 50,
        int $offset = 0,
        string $search = '',
        string $moderation = '',
        string $fullOrdering = 'received_at DESC'
    ): array
    {
        [$orderColumn, $orderDirection] = $this->normaliseOrdering(
            $fullOrdering,
            'received_at',
            'DESC',
            ['received_at', 'id', 'status']
        );

        $query = $this->db->createQuery()
            ->select([
                'i.*',
                $this->db->quoteName('ia.moderation_state', 'reply_moderation_state'),
                $this->db->quoteName('ia.object_id', 'reply_object_id'),
            ])
            ->from($this->db->quoteName('#__fediverse_inbox', 'i'))
            ->leftJoin(
                $this->db->quoteName('#__fediverse_inbound_activities', 'ia')
                . ' ON ' . $this->db->quoteName('ia.inbox_id') . ' = ' . $this->db->quoteName('i.id')
                . ' AND ' . $this->db->quoteName('ia.activity_type') . ' = ' . $this->db->quote('Create')
            )
            ->order($this->db->quoteName('i.' . $orderColumn) . ' ' . $orderDirection);

        if ($status !== '') {
            $query->where($this->db->quoteName('i.status') . ' = :status')
                ->bind(':status', $status, ParameterType::STRING);
        }

        if (in_array($moderation, ['pending', 'approved', 'rejected'], true)) {
            $query->where($this->db->quoteName('ia.moderation_state') . ' = :moderation')
                ->bind(':moderation', $moderation, ParameterType::STRING);
        }

        $search = trim($search);
        if ($search !== '') {
            $searchLike = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . 'CAST(' . $this->db->quoteName('i.id') . ' AS CHAR) LIKE :search1'
                . ' OR ' . $this->db->quoteName('i.activity_id_uri') . ' LIKE :search2'
                . ' OR ' . $this->db->quoteName('i.type') . ' LIKE :search3'
                . ' OR ' . $this->db->quoteName('i.error') . ' LIKE :search4'
                . ')'
            )
                ->bind(':search1', $searchLike, ParameterType::STRING)
                ->bind(':search2', $searchLike, ParameterType::STRING)
                ->bind(':search3', $searchLike, ParameterType::STRING)
                ->bind(':search4', $searchLike, ParameterType::STRING);
        }

        if ($limit > 0) {
            $query->setLimit($limit, $offset);
        }

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }

    /**
     * Count inbox rows for an optional status filter.
     *
     * @params string $status Optional status filter.
     * @params string $search Optional search term.
     * @params string $moderation Optional moderation filter.
     *
     * @return  int  Total matching rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function countList(string $status = '', string $search = '', string $moderation = ''): int
    {
        $query = $this->db->createQuery()
            ->select('COUNT(DISTINCT ' . $this->db->quoteName('i.id') . ')')
            ->from($this->db->quoteName('#__fediverse_inbox', 'i'))
            ->leftJoin(
                $this->db->quoteName('#__fediverse_inbound_activities', 'ia')
                . ' ON ' . $this->db->quoteName('ia.inbox_id') . ' = ' . $this->db->quoteName('i.id')
                . ' AND ' . $this->db->quoteName('ia.activity_type') . ' = ' . $this->db->quote('Create')
            );

        if ($status !== '') {
            $query->where($this->db->quoteName('i.status') . ' = :status')
                ->bind(':status', $status, ParameterType::STRING);
        }

        if (in_array($moderation, ['pending', 'approved', 'rejected'], true)) {
            $query->where($this->db->quoteName('ia.moderation_state') . ' = :moderation')
                ->bind(':moderation', $moderation, ParameterType::STRING);
        }

        $search = trim($search);
        if ($search !== '') {
            $searchLike = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . 'CAST(' . $this->db->quoteName('i.id') . ' AS CHAR) LIKE :search1'
                . ' OR ' . $this->db->quoteName('i.activity_id_uri') . ' LIKE :search2'
                . ' OR ' . $this->db->quoteName('i.type') . ' LIKE :search3'
                . ' OR ' . $this->db->quoteName('i.error') . ' LIKE :search4'
                . ')'
            )
                ->bind(':search1', $searchLike, ParameterType::STRING)
                ->bind(':search2', $searchLike, ParameterType::STRING)
                ->bind(':search3', $searchLike, ParameterType::STRING)
                ->bind(':search4', $searchLike, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $total = $this->db->loadResult();

        return $total !== null ? (int) $total : 0;
    }

    /**
     * Delete inbox rows by id.
     *
     * @params array<int, int> $ids Inbox ids to delete.
     *
     * @return  int  Number of deleted rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        if ($ids === []) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_inbox'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    /**
     * Update moderation state for reply activities selected by inbox ids.
     *
     * @params array<int,int> $ids Inbox ids.
     * @params string $moderationState Target moderation state.
     *
     * @return  int  Number of updated rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function updateReplyModerationByInboxIds(array $ids, string $moderationState): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        if (
            !in_array($moderationState, ['pending', 'approved', 'rejected'], true)
            || $ids === []
        ) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_inbound_activities'))
            ->set($this->db->quoteName('moderation_state') . ' = :state')
            ->where($this->db->quoteName('activity_type') . ' = ' . $this->db->quote('Create'))
            ->where($this->db->quoteName('inbox_id') . ' IN (' . implode(',', $ids) . ')')
            ->bind(':state', $moderationState, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        $affectedRows = (int) $this->db->getAffectedRows();
        $this->syncReplyCommentsWorkflowByInboxIds($ids, $moderationState);

        return $affectedRows;
    }

    /**
     * Sync mapped reply comments workflow entries for selected inbox ids.
     *
     * @params array<int,int> $ids Inbox ids.
     * @params string $moderationState Target moderation state.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function syncReplyCommentsWorkflowByInboxIds(array $ids, string $moderationState): void
    {
        if ($moderationState === 'approved') {
            $this->upsertApprovedReplyCommentsByInboxIds($ids);

            return;
        }

        $this->propagateReplyCommentStateByInboxIds($ids, $moderationState);
    }

    /**
     * Upsert mapped reply comments for approved Create activities.
     *
     * Insert newly approved replies and refresh existing mapped rows.
     *
     * @params array<int,int> $ids Inbox ids.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function upsertApprovedReplyCommentsByInboxIds(array $ids): void
    {
        $inboundTable  = $this->db->quoteName('#__fediverse_inbound_activities');
        $commentsTable = $this->db->quoteName('#__fediverse_comments');
        $idList        = implode(',', $ids);

        $insertSql = 'INSERT IGNORE INTO ' . $commentsTable
            . ' ('
            . implode(
                ',',
                [
                    $this->db->quoteName('inbound_activity_id'),
                    $this->db->quoteName('inbox_id'),
                    $this->db->quoteName('local_actor_id'),
                    $this->db->quoteName('remote_actor_id'),
                    $this->db->quoteName('object_id'),
                    $this->db->quoteName('activity_id_uri'),
                    $this->db->quoteName('raw_json'),
                    $this->db->quoteName('state'),
                ]
            )
            . ') '
            . 'SELECT '
            . implode(
                ',',
                [
                    $this->db->quoteName('ia.id'),
                    $this->db->quoteName('ia.inbox_id'),
                    $this->db->quoteName('ia.local_actor_id'),
                    $this->db->quoteName('ia.remote_actor_id'),
                    $this->db->quoteName('ia.object_id'),
                    $this->db->quoteName('ia.activity_id_uri'),
                    $this->db->quoteName('ia.raw_json'),
                    $this->db->quote('approved'),
                ]
            )
            . ' FROM ' . $inboundTable . ' AS ' . $this->db->quoteName('ia')
            . ' WHERE ' . $this->db->quoteName('ia.activity_type') . ' = ' . $this->db->quote('Create')
            . ' AND ' . $this->db->quoteName('ia.moderation_state') . ' = ' . $this->db->quote('approved')
            . ' AND ' . $this->db->quoteName('ia.inbox_id') . ' IN (' . $idList . ')';

        $this->db->setQuery($insertSql);
        $this->db->execute();

        $updateSql = 'UPDATE ' . $commentsTable . ' AS ' . $this->db->quoteName('c')
            . ' INNER JOIN ' . $inboundTable . ' AS ' . $this->db->quoteName('ia')
            . ' ON ' . $this->db->quoteName('ia.id') . ' = ' . $this->db->quoteName('c.inbound_activity_id')
            . ' SET '
            . $this->db->quoteName('c.object_id') . ' = ' . $this->db->quoteName('ia.object_id')
            . ', ' . $this->db->quoteName('c.activity_id_uri') . ' = ' . $this->db->quoteName('ia.activity_id_uri')
            . ', ' . $this->db->quoteName('c.raw_json') . ' = ' . $this->db->quoteName('ia.raw_json')
            . ', ' . $this->db->quoteName('c.state') . ' = ' . $this->db->quote('approved')
            . ' WHERE ' . $this->db->quoteName('ia.activity_type') . ' = ' . $this->db->quote('Create')
            . ' AND ' . $this->db->quoteName('ia.moderation_state') . ' = ' . $this->db->quote('approved')
            . ' AND ' . $this->db->quoteName('ia.inbox_id') . ' IN (' . $idList . ')';

        $this->db->setQuery($updateSql);
        $this->db->execute();
    }

    /**
     * Propagate pending/rejected moderation state into mapped comments.
     *
     * Update existing comment-workflow rows for the selected inbox ids.
     *
     * @params array<int,int> $ids Inbox ids.
     * @params string $moderationState Target moderation state.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function propagateReplyCommentStateByInboxIds(array $ids, string $moderationState): void
    {
        $inboundTable  = $this->db->quoteName('#__fediverse_inbound_activities');
        $commentsTable = $this->db->quoteName('#__fediverse_comments');
        $idList        = implode(',', $ids);

        $sql = 'UPDATE ' . $commentsTable . ' AS ' . $this->db->quoteName('c')
            . ' INNER JOIN ' . $inboundTable . ' AS ' . $this->db->quoteName('ia')
            . ' ON ' . $this->db->quoteName('ia.id') . ' = ' . $this->db->quoteName('c.inbound_activity_id')
            . ' SET ' . $this->db->quoteName('c.state') . ' = ' . $this->db->quote($moderationState)
            . ' WHERE ' . $this->db->quoteName('ia.activity_type') . ' = ' . $this->db->quote('Create')
            . ' AND ' . $this->db->quoteName('ia.inbox_id') . ' IN (' . $idList . ')';

        $this->db->setQuery($sql);
        $this->db->execute();
    }

    /**
     * Normalise full ordering input to a whitelisted column and direction.
     *
     * @param   string    $fullOrdering      User supplied ordering value.
     * @param   string    $defaultColumn     Fallback ordering column.
     * @param   string    $defaultDirection  Fallback ordering direction.
     * @param   string[]  $allowedColumns    Allowed ordering columns.
     *
     * @return  array{0:string,1:string}  Safe ordering tuple.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normaliseOrdering(
        string $fullOrdering,
        string $defaultColumn,
        string $defaultDirection,
        array $allowedColumns
    ): array {
        $parts     = preg_split('/\s+/', trim($fullOrdering)) ?: [];
        $column    = (string) ($parts[0] ?? '');
        $direction = strtoupper((string) ($parts[1] ?? ''));

        if (!in_array($column, $allowedColumns, true)) {
            $column = $defaultColumn;
        }

        if ($direction !== 'ASC' && $direction !== 'DESC') {
            $direction = strtoupper($defaultDirection);
        }

        return [$column, $direction];
    }
}
