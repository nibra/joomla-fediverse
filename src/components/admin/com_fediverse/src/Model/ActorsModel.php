<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use InvalidArgumentException;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * ActorsModel Class
 *
 * Provide database access for Actors data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorsModel extends BaseModel
{
    /**
     * Initialize the actors model.
     *
     * Store the database dependency for actor persistence operations.
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
     * Fetch an actor by id.
     *
     * Load a single actor record for the provided identifier.
     *
     * @params int $id Actor identifier.
     *
     * @return  ?Actor  Actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getById(int $id): ?Actor
    {
        $aid   = $id;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $aid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? Actor::fromDbRow($row) : null;
    }

    /**
     * Fetch a local actor by user id.
     *
     * Load the local actor associated with a Joomla user.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  ?Actor  Actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getLocalByUserId(int $userId): ?Actor
    {
        $uid   = $userId;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('local'))
            ->where($this->db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $uid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? Actor::fromDbRow($row) : null;
    }

    /**
     * Fetch a local actor by handle.
     *
     * Load a local actor using the stable handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?Actor  Actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getLocalByHandle(string $handle): ?Actor
    {
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('local'))
            ->where($this->db->quoteName('handle') . ' = :h')
            ->bind(':h', $handle);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? Actor::fromDbRow($row) : null;
    }

    /**
     * Fetch a local actor by URI.
     *
     * Load a local actor using the canonical URI.
     *
     * @params string $uri Actor URI.
     *
     * @return  ?Actor  Actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getLocalByUri(string $uri): ?Actor
    {
        $u     = $uri;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('local'))
            ->where($this->db->quoteName('uri') . ' = :u')
            ->bind(':u', $u, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? Actor::fromDbRow($row) : null;
    }

    /**
     * Fetch a remote actor by URI.
     *
     * Load a remote actor using the canonical URI.
     *
     * @params string $uri Actor URI.
     *
     * @return  ?Actor  Actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getRemoteByUri(string $uri): ?Actor
    {
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('remote'))
            ->where($this->db->quoteName('uri') . ' = :u')
            ->bind(':u', $uri);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? Actor::fromDbRow($row) : null;
    }

    /**
     * Resolve a local actor id by handle.
     *
     * Look up the local actor identifier for a handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?int  Actor id or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resolveLocalActorIdByHandle(string $handle): ?int
    {
        $h     = $handle;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('local'))
            ->where($this->db->quoteName('handle') . ' = :h')
            ->bind(':h', $h, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $id = $this->db->loadResult();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Insert a local actor.
     *
     * Persist a new local actor record and return its id.
     *
     * @params Actor $actor Actor instance to insert.
     *
     * @return  int  Inserted actor id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertLocal(Actor $actor): int
    {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_actors'))
            ->columns([
                $this->db->quoteName('type'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('handle'),
                $this->db->quoteName('preferred_username'),
                $this->db->quoteName('uri'),
                $this->db->quoteName('inbox_url'),
                $this->db->quoteName('outbox_url'),
                $this->db->quoteName('shared_inbox_url'),
                $this->db->quoteName('public_key_pem'),
                $this->db->quoteName('profile_json'),
                $this->db->quoteName('is_enabled'),
                $this->db->quoteName('created_at'),
                $this->db->quoteName('updated_at'),
            ])
            ->values(
                implode(',', [
                    $this->db->quote('local'),
                    (string) ((int) ($actor->userId ?? 0)),
                    $this->db->quote($actor->handle),
                    $this->db->quote($actor->preferredUsername),
                    $this->db->quote($actor->uri),
                    $this->db->quote($actor->inboxUrl),
                    $this->db->quote($actor->outboxUrl),
                    $actor->sharedInboxUrl !== null ? $this->db->quote($actor->sharedInboxUrl) : 'NULL',
                    $actor->publicKeyPem !== null ? $this->db->quote($actor->publicKeyPem) : 'NULL',
                    $actor->profileJson !== null ? $this->db->quote($actor->profileJson) : 'NULL',
                    (string) ((int) $actor->isEnabled),
                    'NOW()',
                    'NOW()',
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Update mutable core fields for a local actor.
     *
     * Keep the handle stable while refreshing name and URL fields.
     *
     * @params int $actorId Actor identifier.
     * @params Actor $actor Actor instance with updated fields.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function updateLocalCoreFields(int $actorId, Actor $actor): void
    {
        $id = $actorId;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_actors'))
            ->set($this->db->quoteName('preferred_username') . ' = :pu')
            ->set($this->db->quoteName('uri') . ' = :uri')
            ->set($this->db->quoteName('inbox_url') . ' = :inbox')
            ->set($this->db->quoteName('outbox_url') . ' = :outbox')
            ->set($this->db->quoteName('shared_inbox_url') . ' = :shared')
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id');

        $pu     = $actor->preferredUsername;
        $uri    = $actor->uri;
        $inbox  = $actor->inboxUrl;
        $outbox = $actor->outboxUrl;
        $shared = $actor->sharedInboxUrl ?? '';

        $query->bind(':pu', $pu, ParameterType::STRING)
            ->bind(':uri', $uri, ParameterType::STRING)
            ->bind(':inbox', $inbox, ParameterType::STRING)
            ->bind(':outbox', $outbox, ParameterType::STRING)
            ->bind(':shared', $shared, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Upsert a remote actor.
     *
     * Update an existing remote actor or insert a new one.
     *
     * @params Actor $actor Remote actor instance.
     *
     * @return  int  Actor id.
     * @throws  InvalidArgumentException  if the actor is not remote.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function upsertRemote(Actor $actor): int
    {
        if ($actor->type !== 'remote') {
            throw new InvalidArgumentException('upsertRemote expects type=remote');
        }

        if ($actor->id !== null) {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_actors'))
                ->set($this->db->quoteName('handle') . ' = ' . $this->db->quote($actor->handle))
                ->set($this->db->quoteName('preferred_username') . ' = ' . $this->db->quote($actor->preferredUsername))
                ->set($this->db->quoteName('inbox_url') . ' = ' . $this->db->quote($actor->inboxUrl))
                ->set($this->db->quoteName('outbox_url') . ' = ' . $this->db->quote($actor->outboxUrl))
                ->set($this->db->quoteName('shared_inbox_url') . ' = ' . $this->db->quote($actor->sharedInboxUrl))
                ->set($this->db->quoteName('public_key_pem') . ' = ' . $this->db->quote($actor->publicKeyPem))
                ->set($this->db->quoteName('profile_json') . ' = ' . $this->db->quote($actor->profileJson))
                ->set($this->db->quoteName('is_enabled') . ' = ' . ($actor->isEnabled ? 1 : 0))
                ->where($this->db->quoteName('id') . ' = ' . (int) $actor->id);

            $this->db->setQuery($query);
            $this->db->execute();

            return (int) $actor->id;
        }

        $existing = $this->getRemoteByUri($actor->uri);
        if ($existing) {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__fediverse_actors'))
                ->set($this->db->quoteName('handle') . ' = ' . $this->db->quote($actor->handle))
                ->set($this->db->quoteName('preferred_username') . ' = ' . $this->db->quote($actor->preferredUsername))
                ->set($this->db->quoteName('inbox_url') . ' = ' . $this->db->quote($actor->inboxUrl))
                ->set($this->db->quoteName('outbox_url') . ' = ' . $this->db->quote($actor->outboxUrl))
                ->set($this->db->quoteName('shared_inbox_url') . ' = ' . $this->db->quote($actor->sharedInboxUrl))
                ->set($this->db->quoteName('public_key_pem') . ' = ' . $this->db->quote($actor->publicKeyPem))
                ->set($this->db->quoteName('profile_json') . ' = ' . $this->db->quote($actor->profileJson))
                ->set($this->db->quoteName('is_enabled') . ' = ' . ($actor->isEnabled ? 1 : 0))
                ->where($this->db->quoteName('id') . ' = ' . (int) $existing->id);

            $this->db->setQuery($query);
            $this->db->execute();

            return (int) $existing->id;
        }

        $obj = (object) [
            'type'               => 'remote',
            'user_id'            => null,
            'handle'             => $actor->handle,
            'preferred_username' => $actor->preferredUsername,
            'uri'                => $actor->uri,
            'inbox_url'          => $actor->inboxUrl,
            'outbox_url'         => $actor->outboxUrl,
            'shared_inbox_url'   => $actor->sharedInboxUrl,
            'public_key_pem'     => $actor->publicKeyPem,
            'profile_json'       => $actor->profileJson,
            'is_enabled'         => $actor->isEnabled ? 1 : 0,
        ];

        $this->db->insertObject('#__fediverse_actors', $obj);

        return (int) $this->db->insertid();
    }

    /**
     * Set the public key for an actor.
     *
     * Update the stored public key PEM for the actor.
     *
     * @params int $actorId Actor identifier.
     * @params string $publicKeyPem PEM-encoded public key.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setPublicKeyPem(int $actorId, string $publicKeyPem): void
    {
        $id    = $actorId;
        $pk    = $publicKeyPem;
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_actors'))
            ->set($this->db->quoteName('public_key_pem') . ' = :pk')
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':pk', $pk, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Set the preferred username for an actor.
     *
     * Update the stored preferred username for the actor.
     *
     * @params int $actorId Actor identifier.
     * @params string $preferredUsername Preferred username value.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setPreferredUsername(int $actorId, string $preferredUsername): void
    {
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_actors'))
            ->set($this->db->quoteName('preferred_username') . ' = :pu')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':pu', $preferredUsername)
            ->bind(':id', $actorId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Disable an actor.
     *
     * Mark the actor as disabled.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disable(int $actorId): void
    {
        $this->setEnabled($actorId, false);
    }

    /**
     * Enable an actor.
     *
     * Mark the actor as enabled.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enable(int $actorId): void
    {
        $this->setEnabled($actorId, true);
    }

    /**
     * Set the enabled state for an actor.
     *
     * Update the is_enabled flag for the actor.
     *
     * @params int $actorId Actor identifier.
     * @params bool $enabled Enabled state.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setEnabled(int $actorId, $enabled = false): void
    {
        $id    = $actorId;
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_actors'))
            ->set($this->db->quoteName('is_enabled') . ' = ' . ($enabled ? 1 : 0))
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Update actor_type and object_type for a local actor.
     *
     * Validate the supplied type values and persist them to the database.
     *
     * @params int    $actorId    Actor identifier.
     * @params string $actorType  ActivityPub actor type (Person or Service).
     * @params string $objectType ActivityPub object type (Note, Article, Image, or Video).
     *
     * @return  void  None.
     * @throws  \InvalidArgumentException  When actorType or objectType is not a recognised value.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function updateTypes(int $actorId, string $actorType, string $objectType): void
    {
        $allowedActorTypes  = ['Person', 'Service'];
        $allowedObjectTypes = ['Note', 'Article', 'Image', 'Video'];

        if (!\in_array($actorType, $allowedActorTypes, true)) {
            throw new InvalidArgumentException(
                \sprintf('Invalid actor_type "%s". Allowed: %s', $actorType, \implode(', ', $allowedActorTypes))
            );
        }

        if (!\in_array($objectType, $allowedObjectTypes, true)) {
            throw new InvalidArgumentException(
                \sprintf('Invalid object_type "%s". Allowed: %s', $objectType, \implode(', ', $allowedObjectTypes))
            );
        }

        $id  = $actorId;
        $at  = $actorType;
        $ot  = $objectType;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_actors'))
            ->set($this->db->quoteName('actor_type') . ' = :at')
            ->set($this->db->quoteName('object_type') . ' = :ot')
            ->set($this->db->quoteName('updated_at') . ' = NOW()')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':at', $at, ParameterType::STRING)
            ->bind(':ot', $ot, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Fetch a paginated list of actors.
     *
     * @params string $type   Optional type filter ('local' or 'remote'). Empty for all.
     * @params int    $limit  Maximum number of rows.
     * @params int    $offset Row offset.
     *
     * @return  array<int, array<string, mixed>>  List of actor rows.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getList(string $type = '', int $limit = 50, int $offset = 0): array
    {
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_actors'))
            ->order($this->db->quoteName('preferred_username') . ' ASC');

        if ($type !== '') {
            $query->where($this->db->quoteName('type') . ' = :type')
                ->bind(':type', $type, ParameterType::STRING);
        }

        $query->setLimit($limit, $offset);

        $this->db->setQuery($query);

        return $this->db->loadAssocList() ?: [];
    }
}
