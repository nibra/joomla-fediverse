<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Model;

use DateTimeInterface;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * OAuthModel Class
 *
 * Provide database access for OAuth clients, authorization codes, and tokens.
 *
 * @since  __DEPLOY_VERSION__
 */
final class OAuthModel extends BaseModel
{
    /**
     * Initialize the OAuth model.
     *
     * @params DatabaseInterface $db     Database connection.
     * @params array             $config Model configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private DatabaseInterface $db, array $config = [])
    {
        parent::__construct($config);
    }

    // -------------------------------------------------------------------------
    // Client methods
    // -------------------------------------------------------------------------

    /**
     * Fetch a client by client id.
     *
     * @params string $clientId OAuth client id.
     *
     * @return  ?object  Client record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getByClientId(string $clientId): ?object
    {
        $cid   = $clientId;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_oauth_clients'))
            ->where($this->db->quoteName('client_id') . ' = :cid')
            ->bind(':cid', $cid, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Insert a new OAuth client.
     *
     * @params string  $clientId       Client identifier.
     * @params string  $clientSecret   Client secret.
     * @params string  $redirectUris   JSON-encoded redirect URIs.
     * @params string  $scopes         Allowed scopes.
     * @params ?string $name           Display name.
     * @params ?string $website        Website URL.
     * @params bool    $isConfidential Whether the client is confidential.
     *
     * @return  int  Inserted row id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertClient(
        string $clientId,
        string $clientSecret,
        string $redirectUris,
        string $scopes,
        ?string $name,
        ?string $website,
        bool $isConfidential = true
    ): int {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_oauth_clients'))
            ->columns([
                $this->db->quoteName('client_id'),
                $this->db->quoteName('client_secret'),
                $this->db->quoteName('redirect_uris'),
                $this->db->quoteName('scopes'),
                $this->db->quoteName('name'),
                $this->db->quoteName('website'),
                $this->db->quoteName('is_confidential'),
            ])
            ->values(
                implode(',', [
                    $this->db->quote($clientId),
                    $this->db->quote($clientSecret),
                    $this->db->quote($redirectUris),
                    $this->db->quote($scopes),
                    $name !== null ? $this->db->quote($name) : 'NULL',
                    $website !== null ? $this->db->quote($website) : 'NULL',
                    $isConfidential ? '1' : '0',
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    // -------------------------------------------------------------------------
    // Authorization code methods
    // -------------------------------------------------------------------------

    /**
     * Insert a new authorization code.
     *
     * @params int               $clientId    Client identifier.
     * @params int               $userId      Joomla user identifier.
     * @params string            $codeHash    SHA-256 hash of the code.
     * @params string            $redirectUri Redirect URI.
     * @params string            $scope       Granted scope.
     * @params DateTimeInterface $expiresAt   Expiration timestamp.
     *
     * @return  int  Inserted row id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertCode(
        int $clientId,
        int $userId,
        string $codeHash,
        string $redirectUri,
        string $scope,
        DateTimeInterface $expiresAt
    ): int {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_oauth_authcodes'))
            ->columns([
                $this->db->quoteName('client_id'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('code_hash'),
                $this->db->quoteName('redirect_uri'),
                $this->db->quoteName('scope'),
                $this->db->quoteName('expires_at'),
            ])
            ->values(
                implode(',', [
                    (string) $clientId,
                    (string) $userId,
                    $this->db->quote($codeHash),
                    $this->db->quote($redirectUri),
                    $this->db->quote($scope),
                    $this->db->quote($expiresAt->format('Y-m-d H:i:s')),
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Fetch a code by hash.
     *
     * @params string $codeHash Code hash.
     *
     * @return  ?object  Auth code record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getByCodeHash(string $codeHash): ?object
    {
        $hash  = $codeHash;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_oauth_authcodes'))
            ->where($this->db->quoteName('code_hash') . ' = :hash')
            ->bind(':hash', $hash, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Delete an authorization code by id.
     *
     * @params int $id Auth code id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteAuthCodeById(int $id): void
    {
        $cid   = $id;
        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_oauth_authcodes'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $cid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    // -------------------------------------------------------------------------
    // Token methods
    // -------------------------------------------------------------------------

    /**
     * Insert an OAuth token row.
     *
     * @params int               $clientId    Client identifier.
     * @params int               $userId      Joomla user identifier.
     * @params string            $accessHash  Access token hash.
     * @params ?string           $refreshHash Refresh token hash.
     * @params string            $scope       Granted scope.
     * @params DateTimeInterface $expiresAt   Access token expiration.
     *
     * @return  int  Inserted row id.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function insertToken(
        int $clientId,
        int $userId,
        string $accessHash,
        ?string $refreshHash,
        string $scope,
        DateTimeInterface $expiresAt
    ): int {
        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__fediverse_oauth_tokens'))
            ->columns([
                $this->db->quoteName('client_id'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('access_token_hash'),
                $this->db->quoteName('refresh_token_hash'),
                $this->db->quoteName('scope'),
                $this->db->quoteName('expires_at'),
            ])
            ->values(
                implode(',', [
                    (string) $clientId,
                    (string) $userId,
                    $this->db->quote($accessHash),
                    $refreshHash !== null ? $this->db->quote($refreshHash) : 'NULL',
                    $this->db->quote($scope),
                    $this->db->quote($expiresAt->format('Y-m-d H:i:s')),
                ])
            );

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Fetch a token by access hash.
     *
     * @params string $hash Access token hash.
     *
     * @return  ?object  Token record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getByAccessTokenHash(string $hash): ?object
    {
        $h     = $hash;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_oauth_tokens'))
            ->where($this->db->quoteName('access_token_hash') . ' = :h')
            ->bind(':h', $h, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Fetch a token by refresh hash.
     *
     * @params string $hash Refresh token hash.
     *
     * @return  ?object  Token record or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getByRefreshTokenHash(string $hash): ?object
    {
        $h     = $hash;
        $query = $this->db->createQuery()
            ->select('*')
            ->from($this->db->quoteName('#__fediverse_oauth_tokens'))
            ->where($this->db->quoteName('refresh_token_hash') . ' = :h')
            ->bind(':h', $h, ParameterType::STRING)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Delete a token by id.
     *
     * @params int $id Token id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deleteTokenById(int $id): void
    {
        $tid   = $id;
        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__fediverse_oauth_tokens'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $tid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
