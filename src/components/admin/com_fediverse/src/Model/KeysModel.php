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
 * KeysModel Class
 *
 * Provide database access for Keys data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeysModel extends BaseModel
{
    /**
     * Initialize the keys model.
     *
     * Store the database dependency for key management operations.
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
     * Fetch active key metadata for an actor.
     *
     * Load the active key id and payloads for signing and verification.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  ?object  Active key metadata or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getActiveKeyMeta(int $actorId): ?object
    {
        $id    = $actorId;
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('key_id_uri'),
                $this->db->quoteName('public_key_pem'),
                $this->db->quoteName('private_key_enc'),
            ])
            ->from($this->db->quoteName('#__fediverse_keys'))
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':id', $id, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' DESC')
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Fetch active key metadata by key id URI.
     *
     * Resolve key payloads for the provided key identifier.
     *
     * @params string $keyIdUri Key identifier URI.
     *
     * @return  ?object  Key metadata or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getByKeyIdUri(string $keyIdUri): ?object
    {
        $kid   = $keyIdUri;
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('key_id_uri'),
                $this->db->quoteName('public_key_pem'),
                $this->db->quoteName('private_key_enc'),
            ])
            ->from($this->db->quoteName('#__fediverse_keys'))
            ->where($this->db->quoteName('key_id_uri') . ' = :kid')
            ->where(
                $this->db->quoteName('status')
                . ' IN (' . $this->db->quote('active') . ',' . $this->db->quote('rotated') . ')'
            )
            ->bind(':kid', $kid, ParameterType::STRING)
            ->order(
                'CASE WHEN '
                . $this->db->quoteName('status')
                . ' = '
                . $this->db->quote('active')
                . ' THEN 0 ELSE 1 END'
            )
            ->order($this->db->quoteName('id') . ' DESC')
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Fetch the latest rotated key id for an actor.
     *
     * Return the most recent rotated key identifier when present.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  ?string  Rotated key id or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getLatestRotatedKeyId(int $actorId): ?string
    {
        $id    = $actorId;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('key_id_uri'))
            ->from($this->db->quoteName('#__fediverse_keys'))
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('rotated'))
            ->bind(':id', $id, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' DESC')
            ->setLimit(1);

        $this->db->setQuery($query);
        $kid = $this->db->loadResult();

        return is_string($kid) && $kid !== '' ? $kid : null;
    }

    /**
     * Fetch the created timestamp for the active key.
     *
     * Return the creation time of the active key row for rotation checks.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  ?string  Created timestamp or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getActiveKeyCreatedAt(int $actorId): ?string
    {
        $id    = $actorId;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('created_at'))
            ->from($this->db->quoteName('#__fediverse_keys'))
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':id', $id, ParameterType::INTEGER)
            ->order($this->db->quoteName('id') . ' DESC')
            ->setLimit(1);

        $this->db->setQuery($query);
        $value = $this->db->loadResult();

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Update the active key id URI for an actor.
     *
     * Assign a new key id URI to the active key row.
     *
     * @params int $actorId Actor identifier.
     * @params string $keyIdUri New key identifier URI.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function updateActiveKeyIdUri(int $actorId, string $keyIdUri): void
    {
        $id  = $actorId;
        $kid = $keyIdUri;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set($this->db->quoteName('key_id_uri') . ' = :kid')
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':kid', $kid, ParameterType::STRING)
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Update an active key id URI by its current key id.
     *
     * Assign a new key id URI using the existing key id as selector.
     *
     * @params string $currentKeyIdUri Current key identifier URI.
     * @params string $newKeyIdUri New key identifier URI.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function updateActiveKeyIdUriByKeyId(string $currentKeyIdUri, string $newKeyIdUri): void
    {
        $current = $currentKeyIdUri;
        $next    = $newKeyIdUri;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set($this->db->quoteName('key_id_uri') . ' = :next')
            ->where($this->db->quoteName('key_id_uri') . ' = :current')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':next', $next, ParameterType::STRING)
            ->bind(':current', $current, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Upgrade legacy active key ids.
     *
     * Append a unique suffix to active keys using the legacy #main-key id.
     *
     * @return  int  Number of rows updated.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function upgradeLegacyActiveKeyIds(): int
    {
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set(
                $this->db->quoteName('key_id_uri')
                . ' = CONCAT(' . $this->db->quoteName('key_id_uri') . ', '
                . $this->db->quote('-') . ', ' . $this->db->quoteName('id') . ', '
                . $this->db->quote('-') . ', UNIX_TIMESTAMP())'
            )
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->where($this->db->quoteName('key_id_uri') . ' LIKE ' . $this->db->quote('%#main-key'));

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->getAffectedRows();
    }

    /**
     * Revoke active keys for an actor.
     *
     * Mark all active keys as revoked and set rotation timestamps.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function revokeActiveKeysForActor(int $actorId): void
    {
        $id    = $actorId;
        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('revoked'))
            ->set($this->db->quoteName('rotated_at') . ' = NOW()')
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Rotate active keys for an actor.
     *
     * Mark active keys as rotated and adjust key ids to keep them unique.
     *
     * @params int $actorId Actor identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateActiveKeysForActor(int $actorId): void
    {
        $id = $actorId;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('rotated'))
            ->set($this->db->quoteName('rotated_at') . ' = NOW()')
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('active'))
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Release a key id URI for reuse.
     *
     * Adjust existing rows to avoid unique key id collisions.
     *
     * @params int $actorId Actor identifier.
     * @params string $keyIdUri Key identifier URI to release.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function releaseKeyIdUriForActor(int $actorId, string $keyIdUri): void
    {
        $id  = $actorId;
        $kid = $keyIdUri;

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__fediverse_keys'))
            ->set(
                $this->db->quoteName('key_id_uri')
                . ' = CONCAT(' . $this->db->quoteName('key_id_uri') . ', '
                . $this->db->quote(':previous-') . ', ' . $this->db->quoteName('id') . ', '
                . $this->db->quote('-') . ', UNIX_TIMESTAMP())'
            )
            ->where($this->db->quoteName('actor_id') . ' = :id')
            ->where($this->db->quoteName('key_id_uri') . ' = :kid')
            ->bind(':id', $id, ParameterType::INTEGER)
            ->bind(':kid', $kid, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Insert a new active key.
     *
     * Store key payloads and mark the key as active.
     *
     * @params int $actorId Actor identifier.
     * @params string $keyIdUri Key identifier URI.
     * @params string $privateKeyEnc Encrypted private key payload.
     * @params string $publicKeyPem PEM-encoded public key.
     *
     * @return  int  Inserted key id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertActiveKey(int $actorId, string $keyIdUri, string $privateKeyEnc, string $publicKeyPem): int
    {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_keys'))
            ->columns([
                $this->db->quoteName('actor_id'),
                $this->db->quoteName('key_id_uri'),
                $this->db->quoteName('private_key_enc'),
                $this->db->quoteName('public_key_pem'),
                $this->db->quoteName('status'),
                $this->db->quoteName('created_at'),
            ])
            ->values(
                implode(',', [
                    (string) $actorId,
                    $this->db->quote($keyIdUri),
                    $this->db->quote($privateKeyEnc),
                    $this->db->quote($publicKeyPem),
                    $this->db->quote('active'),
                    'NOW()',
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }
}
