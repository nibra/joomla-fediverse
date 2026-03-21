<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use DateTimeImmutable;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * DeliveryQueueModel Class
 *
 * Provide database access for Delivery Queue data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class DeliveryQueueModel extends BaseModel implements DeliveryQueueModelInterface
{
    private const MAX_CLAIM_CANDIDATES_FACTOR = 10;
    private const MAX_ATTEMPTS                = 10;

    /**
     * Initialize the delivery queue model.
     *
     * Store the database dependency for delivery queue operations.
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
     * Insert delivery jobs for an outbox item.
     *
     * Add queue entries for each target inbox URL.
     *
     * @params int $outboxId Outbox item identifier.
     * @params string[] $targetInboxUrls Target inbox URLs.
     *
     * @return  int  Number of queue entries inserted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function bulkInsert(int $outboxId, array $targetInboxUrls): int
    {
        $targetInboxUrls = array_values(
            array_filter(array_unique($targetInboxUrls), static fn($u) => is_string($u) && trim($u) !== '')
        );
        if ($targetInboxUrls === []) {
            return 0;
        }

        $localActorId = $this->getLocalActorIdByOutboxId($outboxId);

        $inserted = 0;
        foreach ($targetInboxUrls as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $query = $this->db->createQuery()
                ->insert($this->db->quoteName('#__fediverse_delivery_queue'))
                ->columns([
                    $this->db->quoteName('outbox_id'),
                    $this->db->quoteName('local_actor_id'),
                    $this->db->quoteName('target_inbox_url'),
                    $this->db->quoteName('attempts'),
                    $this->db->quoteName('next_attempt_at'),
                    $this->db->quoteName('state'),
                ])
                ->values(
                    implode(',', [
                        (string) $outboxId,
                        (string) $localActorId,
                        $this->db->quote($url),
                        '0',
                        'NOW()',
                        $this->db->quote('queued'),
                    ])
                );

            try {
                $this->db->setQuery($query);
                $this->db->execute();
                $inserted += 1;
            } catch (Throwable) {
                // likely duplicate (unique constraint on (outbox_id,target_inbox_url)); ignore
            }
        }

        return $inserted;
    }

    /**
     * Claim due delivery jobs.
     *
     * Return a list of queue ids that are ready for delivery.
     *
     * @params int $limit Maximum number of jobs to claim.
     *
     * @return  array<int, object>  List of objects with at least an id field.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function claimDue(int $limit): array
    {
        $limit  = max(1, $limit);
        $nowSql = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        try {
            return $this->claimDueSkipLocked($limit, $nowSql);
        } catch (Throwable) {
            return $this->claimDueOptimistic($limit, $nowSql);
        }
    }

    /**
     * Fetch a delivery job by id.
     *
     * Load the delivery job details and associated payload.
     *
     * @params int $jobId Delivery job identifier.
     *
     * @return  mixed  Delivery job record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $jobId): mixed
    {
        $id = $jobId;

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('q.id', 'id'),
                $this->db->quoteName('q.outbox_id', 'outboxId'),
                $this->db->quoteName('q.local_actor_id', 'localActorId'),
                $this->db->quoteName('q.target_inbox_url', 'targetInboxUrl'),
                $this->db->quoteName('o.raw_json', 'payload'),
                '(SELECT ' . $this->db->quoteName('k.key_id_uri')
                . ' FROM ' . $this->db->quoteName('#__fediverse_keys', 'k')
                . ' WHERE ' . $this->db->quoteName('k.actor_id') . ' = q.' . $this->db->quoteName('local_actor_id')
                . ' AND ' . $this->db->quoteName('k.status') . ' = ' . $this->db->quote('active')
                . ' ORDER BY ' . $this->db->quoteName('k.id') . ' DESC LIMIT 1) AS ' . $this->db->quoteName(
                    'localActorKeyId'
                ),
            ])
            ->from($this->db->quoteName('#__fediverse_delivery_queue', 'q'))
            ->innerJoin($this->db->quoteName('#__fediverse_outbox', 'o') . ' ON o.id = q.outbox_id')
            ->where($this->db->quoteName('q.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Mark a delivery job as successful.
     *
     * Update the delivery state and clear error metadata.
     *
     * @params int $jobId Delivery job identifier.
     * @params int $status HTTP status code.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markSuccess(int $jobId, int $status): void
    {
        $id = $jobId;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_delivery_queue'))
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote('delivered'))
            ->set($this->db->quoteName('last_error') . ' = NULL')
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Mark a delivery job as failed and schedule a retry.
     *
     * Update attempts, state, and next attempt timestamp based on retry policy.
     *
     * @params int $jobId Delivery job identifier.
     * @params int $status HTTP status code.
     * @params string $body Response body for diagnostics.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailureAndScheduleRetry(int $jobId, int $status, string $body): void
    {
        $job = $this->getJobMeta($jobId);
        if ($job === null) {
            return;
        }

        $plan = self::computeRetryPlan((int) $job->attempts, new DateTimeImmutable('now'));
        $err  = self::buildHttpErrorMessage($status, $body);

        $id       = $jobId;
        $attempts = $plan['attempts'];
        $next     = $plan['next_attempt_at'];

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_delivery_queue'))
            ->set($this->db->quoteName('attempts') . ' = :attempts')
            ->set($this->db->quoteName('next_attempt_at') . ' = :next')
            ->set($this->db->quoteName('state') . ' = ' . $this->db->quote($plan['state']))
            ->set($this->db->quoteName('last_error') . ' = :err')
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':attempts', $attempts, ParameterType::INTEGER)
            ->bind(':next', $next, ParameterType::STRING)
            ->bind(':err', $err, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Compute a retry plan for a delivery job.
     *
     * Calculate the next attempt time and state for the job.
     *
     * @params int $attemptsSoFar Attempts completed so far.
     * @params DateTimeImmutable $now Current time reference.
     *
     * @return  array{attempts:int,next_attempt_at:string,state:string}  Retry plan details.
     * @throws  \Exception  if randomness for jitter fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function computeRetryPlan(int $attemptsSoFar, DateTimeImmutable $now): array
    {
        $attempts = $attemptsSoFar + 1;
        $isDead   = $attempts >= self::MAX_ATTEMPTS;

        $backoffSeconds = min(3600, (int) (pow(2, min($attempts, 10)) * 10));
        $jitterSeconds  = random_int(0, 30);

        $next = $now->modify('+' . ($backoffSeconds + $jitterSeconds) . ' seconds')->format('Y-m-d H:i:s');

        return [
            'attempts'        => $attempts,
            'next_attempt_at' => $next,
            'state'           => $isDead ? 'dead' : 'queued',
        ];
    }

    /**
     * Build an HTTP error summary.
     *
     * Compose a concise error string from status and response body.
     *
     * @params int $status HTTP status code.
     * @params string $body Response body.
     *
     * @return  string  Error summary for storage.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function buildHttpErrorMessage(int $status, string $body): string
    {
        $err  = 'HTTP ' . $status;
        $body = trim($body);
        if ($body !== '') {
            $err .= ' ' . mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 2000);
        }

        return $err;
    }

    /**
     * Resolve the local actor id for an outbox item.
     *
     * Look up the local actor associated with the outbox entry.
     *
     * @params int $outboxId Outbox item identifier.
     *
     * @return  int  Local actor identifier.
     * @throws  RuntimeException  if the outbox item is missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getLocalActorIdByOutboxId(int $outboxId): int
    {
        $id = $outboxId;

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('local_actor_id'))
            ->from($this->db->quoteName('#__fediverse_outbox'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $actorId = $this->db->loadResult();

        if ($actorId === null) {
            throw new RuntimeException('Outbox item not found: ' . $outboxId);
        }

        return (int) $actorId;
    }

    /**
     * Fetch metadata for a delivery job.
     *
     * Load job attempts and identifiers for retry handling.
     *
     * @params int $jobId Delivery job identifier.
     *
     * @return  ?object  Job metadata or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getJobMeta(int $jobId): ?object
    {
        $id = $jobId;

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('attempts'),
            ])
            ->from($this->db->quoteName('#__fediverse_delivery_queue'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Delete completed delivery queue rows older than the given number of days.
     *
     * @params int $days Retention period in days.
     *
     * @return  int  Number of rows deleted.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteOlderThan(int $days): int
    {
        $cutoff         = (new DateTimeImmutable())->modify('-' . max(1, $days) . ' days')->format('Y-m-d H:i:s');
        $statuses       = ['delivered', 'dead'];
        $quotedStatuses = implode(',', array_map(fn(string $s) => $this->db->quote($s), $statuses));

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_delivery_queue'))
            ->where($this->db->quoteName('updated_at') . ' < :cutoff')
            ->where($this->db->quoteName('state') . ' IN (' . $quotedStatuses . ')')
            ->bind(':cutoff', $cutoff, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Requires server support for SKIP LOCKED.
     *
     * Claim due jobs using row-level locks when supported.
     *
     * @params int $limit Maximum number of jobs to claim.
     * @params string $nowSql Current timestamp in SQL format.
     *
     * @return  array<int, object>  List of objects with at least an id field.
     * @throws  Throwable  if a database operation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function claimDueSkipLocked(int $limit, string $nowSql): array
    {
        $now = $nowSql;

        $this->db->transactionStart();
        try {
            $query = $this->db->createQuery()
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__fediverse_delivery_queue'))
                ->where($this->db->quoteName('state') . ' = ' . $this->db->quote('queued'))
                ->where($this->db->quoteName('next_attempt_at') . ' <= :now')
                ->order($this->db->quoteName('next_attempt_at') . ' ASC')
                ->setLimit($limit)
                ->bind(':now', $now, ParameterType::STRING);

            $sql = (string) $query . ' FOR UPDATE SKIP LOCKED';
            $this->db->setQuery($sql);
            $ids = $this->db->loadColumn() ?: [];

            if ($ids !== []) {
                $idList = implode(',', array_map('intval', $ids));

                $uq = $this->db->createQuery()
                    ->update($this->db->quoteName('#__fediverse_delivery_queue'))
                    ->set($this->db->quoteName('state') . ' = ' . $this->db->quote('inflight'))
                    ->set($this->db->quoteName('updated_at') . ' = NOW()')
                    ->where($this->db->quoteName('id') . ' IN (' . $idList . ')')
                    ->where($this->db->quoteName('state') . ' = ' . $this->db->quote('queued'));

                $this->db->setQuery($uq);
                $this->db->execute();
            }

            $this->db->transactionCommit();

            return array_map(static fn($id) => (object) ['id' => (int) $id], $ids);
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Optimistic claim path: select candidates, then try to flip state row-by-row.
     *
     * Claim due jobs without relying on SKIP LOCKED.
     *
     * @params int $limit Maximum number of jobs to claim.
     * @params string $nowSql Current timestamp in SQL format.
     *
     * @return  array<int, object>  List of objects with at least an id field.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function claimDueOptimistic(int $limit, string $nowSql): array
    {
        $now             = $nowSql;
        $candidatesLimit = $limit * self::MAX_CLAIM_CANDIDATES_FACTOR;

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_delivery_queue'))
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote('queued'))
            ->where($this->db->quoteName('next_attempt_at') . ' <= :now')
            ->order($this->db->quoteName('next_attempt_at') . ' ASC')
            ->setLimit($candidatesLimit)
            ->bind(':now', $now, ParameterType::STRING);

        $this->db->setQuery($query);
        $candidateIds = $this->db->loadColumn() ?: [];

        if ($candidateIds === []) {
            return [];
        }

        $claimed = [];
        foreach ($candidateIds as $candidateId) {
            $id = (int) $candidateId;

            $uq = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_delivery_queue'))
                ->set($this->db->quoteName('state') . ' = ' . $this->db->quote('inflight'))
                ->set($this->db->quoteName('updated_at') . ' = NOW()')
                ->where($this->db->quoteName('id') . ' = :id')
                ->where($this->db->quoteName('state') . ' = ' . $this->db->quote('queued'))
                ->where($this->db->quoteName('next_attempt_at') . ' <= :now')
                ->bind(':id', $id, ParameterType::INTEGER)
                ->bind(':now', $now, ParameterType::STRING);

            $this->db->setQuery($uq);
            $this->db->execute();

            if ($this->db->getAffectedRows() === 1) {
                $claimed[] = $id;
                if (count($claimed) >= $limit) {
                    break;
                }
            }
        }

        return array_map(static fn($id) => (object) ['id' => (int) $id], $claimed);
    }

    /**
     * Fetch a paginated list of delivery queue records.
     *
     * @params string $state   Optional state filter ('queued','inflight','delivered','failed','dead').
     * @params int    $limit   Maximum number of rows.
     * @params int    $offset  Row offset.
     *
     * @return  array<int, array<string, mixed>>  List of delivery queue rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(string $state = '', int $limit = 50, int $offset = 0): array
    {
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_delivery_queue'))
            ->order($this->db->quoteName('created_at') . ' DESC');

        if ($state !== '') {
            $query->where($this->db->quoteName('state') . ' = :state')
                ->bind(':state', $state, ParameterType::STRING);
        }

        $query->setLimit($limit, $offset);

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }
}
