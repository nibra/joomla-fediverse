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
use Throwable;

/**
 * FollowersModel Class
 *
 * Provide database access for Followers data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FollowersModel extends BaseModel implements FollowersModelInterface
{
    /**
     * Initialize the followers model.
     *
     * Store the database dependency for follower persistence operations.
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
     * Fetch accepted follower inbox URLs.
     *
     * Return inbox targets for accepted followers, preferring shared inboxes.
     *
     * @params int $localActorId Local actor identifier.
     *
     * @return  string[]  Inbox URLs for accepted followers.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getAcceptedFollowerInboxUrls(int $localActorId): array
    {
        $lid = $localActorId;

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('a.shared_inbox_url') . ' AS shared_inbox_url',
                $this->db->quoteName('a.inbox_url') . ' AS inbox_url',
            ])
            ->from($this->db->quoteName('#__fediverse_followers', 'f'))
            ->innerJoin($this->db->quoteName('#__fediverse_actors', 'a') . ' ON a.id = f.remote_actor_id')
            ->where('f.' . $this->db->quoteName('local_actor_id') . ' = :lid')
            ->where('f.' . $this->db->quoteName('state') . ' = ' . $this->db->quote('accepted'))
            ->bind(':lid', $lid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $urls = [];
        foreach ($rows as $row) {
            $url = $row->shared_inbox_url ?: $row->inbox_url;
            if ($url) {
                $urls[] = (string) $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Fetch a follower edge state.
     *
     * Return the stored state for a local/remote follower edge.
     *
     * @params int $localActorId Local actor identifier.
     * @params int $remoteActorId Remote actor identifier.
     *
     * @return  ?string  Edge state or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getFollowerState(int $localActorId, int $remoteActorId): ?string
    {
        $lid = $localActorId;
        $rid = $remoteActorId;

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('state'))
            ->from($this->db->quoteName('#__fediverse_followers'))
            ->where($this->db->quoteName('local_actor_id') . ' = :lid')
            ->where($this->db->quoteName('remote_actor_id') . ' = :rid')
            ->bind(':lid', $lid, ParameterType::INTEGER)
            ->bind(':rid', $rid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $state = $this->db->loadResult();

        if ($state === null) {
            return null;
        }

        return (string) $state;
    }

    /**
     * Upsert a follower edge.
     *
     * Ensure a follower relationship exists with the provided state.
     *
     * @params int $localActorId Local actor identifier.
     * @params int $remoteActorId Remote actor identifier.
     * @params string $state Follower state value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function upsertFollowerEdge(int $localActorId, int $remoteActorId, string $state): void
    {
        $lid = $localActorId;
        $rid = $remoteActorId;
        $st  = $state;

        // Try update first
        $uq = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_followers'))
            ->set($this->db->quoteName('state') . ' = :st')
            ->where($this->db->quoteName('local_actor_id') . ' = :lid')
            ->where($this->db->quoteName('remote_actor_id') . ' = :rid')
            ->bind(':st', $st, ParameterType::STRING)
            ->bind(':lid', $lid, ParameterType::INTEGER)
            ->bind(':rid', $rid, ParameterType::INTEGER);

        $this->db->setQuery($uq);
        $this->db->execute();

        if ($this->db->getAffectedRows() > 0) {
            return;
        }

        // Insert
        $iq = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_followers'))
            ->columns([
                $this->db->quoteName('local_actor_id'),
                $this->db->quoteName('remote_actor_id'),
                $this->db->quoteName('state'),
                $this->db->quoteName('created_at'),
            ])
            ->values(
                implode(',', [
                    (string) $lid,
                    (string) $rid,
                    $this->db->quote($st),
                    'NOW()',
                ])
            );

        try {
            $this->db->setQuery($iq);
            $this->db->execute();
        } catch (Throwable) {
            // Ignore concurrent insert or duplicates.
        }
    }

    /**
     * Remove a follower edge.
     *
     * Delete the follower relationship between local and remote actors.
     *
     * @params int $localActorId Local actor identifier.
     * @params int $remoteActorId Remote actor identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function removeFollowerEdge(int $localActorId, int $remoteActorId): void
    {
        $lid = $localActorId;
        $rid = $remoteActorId;

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_followers'))
            ->where($this->db->quoteName('local_actor_id') . ' = :lid')
            ->where($this->db->quoteName('remote_actor_id') . ' = :rid')
            ->bind(':lid', $lid, ParameterType::INTEGER)
            ->bind(':rid', $rid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
