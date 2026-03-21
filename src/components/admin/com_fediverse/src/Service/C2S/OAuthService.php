<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\C2S;

use DateInterval;
use DateTimeImmutable;
use Joomla\Database\DatabaseInterface;
use NX\Component\Fediverse\Administrator\Exception\OAuthException;
use NX\Component\Fediverse\Administrator\Model\OAuthModel;
use NX\Component\Fediverse\Administrator\Model\UsersModel;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;

/**
 * OAuthService Class
 *
 * Provide O Auth services.
 *
 * @since  __DEPLOY_VERSION__
 */

final class OAuthService
{
    private const AUTH_CODE_TTL_SECONDS = 600;
    private const ACCESS_TOKEN_TTL_SECONDS = 7200;

    private UsersModel $usersModel;

    /**
     * Initialize the OAuth service.
     *
     * Store model and helpers.
     *
     * @params OAuthModel      $oauth  OAuth model.
     * @params DatabaseInterface $db   Database connection.
     * @params FediverseConfig $config Component configuration.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private OAuthModel $oauth,
        DatabaseInterface $db,
        private FediverseConfig $config,
    ) {
        $this->usersModel = new UsersModel($db);
    }

    /**
     * Authorize an OAuth request.
     *
     * Validate parameters and return a redirect target with authorization code.
     *
     * @params array $params OAuth request parameters.
     * @params int $joomlaUserId Joomla user identifier.
     *
     * @return  string  Redirect URL containing an authorization code.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function authorize(array $params, int $joomlaUserId): string
    {
        if ($joomlaUserId <= 0) {
            throw new OAuthException('login_required', 'Login required', 401);
        }

        $responseType = strtolower(trim((string) ($params['response_type'] ?? '')));
        if ($responseType !== 'code') {
            throw new OAuthException('unsupported_response_type', 'Only authorization_code is supported', 400);
        }

        $clientId = trim((string) ($params['client_id'] ?? ''));
        $redirectUri = trim((string) ($params['redirect_uri'] ?? ''));
        $state = trim((string) ($params['state'] ?? ''));
        $scope = trim((string) ($params['scope'] ?? ''));

        if ($clientId === '' || $redirectUri === '') {
            throw new OAuthException('invalid_request', 'client_id and redirect_uri are required', 400);
        }

        $client = $this->oauth->getByClientId($clientId);
        if ($client === null) {
            throw new OAuthException('invalid_client', 'Unknown client', 401);
        }

        if (!self::isValidRedirectUri($redirectUri)) {
            throw new OAuthException('invalid_request', 'Invalid redirect_uri', 400);
        }

        $allowed = $this->normalizeRedirectUris((string) ($client->redirect_uris ?? ''));
        if (!self::isRedirectUriAllowed($redirectUri, $allowed)) {
            throw new OAuthException('invalid_request', 'redirect_uri is not registered', 400);
        }

        if ($scope === '') {
            $scope = trim((string) ($client->scopes ?? ''));
        }
        $scope = self::normalizeScope($scope);

        $code = self::generateToken(32);
        $codeHash = hash('sha256', $code);
        $expiresAt = (new DateTimeImmutable('now'))
            ->add(new DateInterval('PT' . self::AUTH_CODE_TTL_SECONDS . 'S'));

        $this->oauth->insertCode(
            (int) $client->id,
            $joomlaUserId,
            $codeHash,
            $redirectUri,
            $scope,
            $expiresAt
        );

        $uri = \Joomla\CMS\Uri\Uri::getInstance($redirectUri);
        $uri->setVar('code', $code);
        if ($state !== '') {
            $uri->setVar('state', $state);
        }

        return $uri->toString();
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * Validate the token request and return access token data.
     *
     * @params array $params OAuth token request parameters.
     *
     * @return  array  Token response payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function token(array $params): array
    {
        $grantType = strtolower(trim((string) ($params['grant_type'] ?? '')));

        if ($grantType === 'authorization_code') {
            return $this->grantAuthorizationCode($params);
        }

        if ($grantType === 'refresh_token') {
            return $this->grantRefreshToken($params);
        }

        throw new OAuthException('unsupported_grant_type', 'Unsupported grant type', 400);
    }

    /**
     * Assert that a token includes a scope.
     *
     * Verify that the provided token grants the requested scope.
     *
     * @params string $token Access token.
     * @params string $scope Required scope.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function assertScope(string $token, string $scope): void
    {
        $record = $this->requireAccessToken($token);
        $granted = self::normalizeScope((string) ($record->scope ?? ''));
        $required = self::normalizeScope($scope);

        if ($required === '') {
            return;
        }

        $grantedSet = array_flip(explode(' ', $granted));
        foreach (explode(' ', $required) as $req) {
            if ($req === '') {
                continue;
            }
            if (!isset($grantedSet[$req])) {
                throw new OAuthException('insufficient_scope', 'Missing scope: ' . $req, 403);
            }
        }
    }

    /**
     * Assert that a token controls an actor.
     *
     * Verify that the provided token has access to the actor handle.
     *
     * @params string $token Access token.
     * @params string $actorHandle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function assertActorControl(string $token, string $actorHandle): void
    {
        $record = $this->requireAccessToken($token);
        $userId = (int) ($record->user_id ?? 0);
        if ($userId <= 0) {
            throw new OAuthException('invalid_token', 'Token has no user', 401);
        }

        $user = $this->usersModel->getById($userId);
        if ($user === null) {
            throw new OAuthException('invalid_token', 'User not found', 401);
        }

        $expected = $this->config->formatActorHandle($userId, (string) ($user->username ?? ''));
        if (strcasecmp($expected, $actorHandle) !== 0) {
            throw new OAuthException('insufficient_scope', 'Token does not control actor', 403);
        }
    }

    /**
     * Require a valid token for an actor and scope.
     *
     * Validate the token, optional scope, and actor ownership in one pass.
     *
     * @params string $token Access token.
     * @params string $actorHandle Actor handle.
     * @params string $scope Required scope (optional).
     *
     * @return  object  Token record.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function requireTokenForActor(string $token, string $actorHandle, string $scope = ''): object
    {
        $record = $this->requireAccessToken($token);

        $required = self::normalizeScope($scope);
        if ($required !== '') {
            $granted = self::normalizeScope((string) ($record->scope ?? ''));
            $grantedSet = array_flip(explode(' ', $granted));
            foreach (explode(' ', $required) as $req) {
                if ($req === '') {
                    continue;
                }
                if (!isset($grantedSet[$req])) {
                    throw new OAuthException('insufficient_scope', 'Missing scope: ' . $req, 403);
                }
            }
        }

        $userId = (int) ($record->user_id ?? 0);
        if ($userId <= 0) {
            throw new OAuthException('invalid_token', 'Token has no user', 401);
        }

        $user = $this->usersModel->getById($userId);
        if ($user === null) {
            throw new OAuthException('invalid_token', 'User not found', 401);
        }

        $expected = $this->config->formatActorHandle($userId, (string) ($user->username ?? ''));
        if (strcasecmp($expected, $actorHandle) !== 0) {
            throw new OAuthException('insufficient_scope', 'Token does not control actor', 403);
        }

        return $record;
    }

    /**
     * Require a valid token for a user and scope.
     *
     * Validate the token, optional scope, and user ownership in one pass.
     *
     * @params string $token Access token.
     * @params string $scope Required scope (optional).
     *
     * @return  object  Joomla user record.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function requireUserForToken(string $token, string $scope = ''): object
    {
        $record = $this->requireAccessToken($token);

        $required = self::normalizeScope($scope);
        if ($required !== '') {
            $granted = self::normalizeScope((string) ($record->scope ?? ''));
            $grantedSet = array_flip(explode(' ', $granted));
            foreach (explode(' ', $required) as $req) {
                if ($req === '') {
                    continue;
                }
                if (!isset($grantedSet[$req])) {
                    throw new OAuthException('insufficient_scope', 'Missing scope: ' . $req, 403);
                }
            }
        }

        $userId = (int) ($record->user_id ?? 0);
        if ($userId <= 0) {
            throw new OAuthException('invalid_token', 'Token has no user', 401);
        }

        $user = $this->usersModel->getById($userId);
        if ($user === null) {
            throw new OAuthException('invalid_token', 'User not found', 401);
        }

        return $user;
    }

    /**
     * Handle the authorization code grant.
     *
     * @params array $params Token request parameters.
     *
     * @return  array  Token response payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function grantAuthorizationCode(array $params): array
    {
        $client = $this->requireClient($params);

        $code = trim((string) ($params['code'] ?? ''));
        $redirectUri = trim((string) ($params['redirect_uri'] ?? ''));

        if ($code === '' || $redirectUri === '') {
            throw new OAuthException('invalid_request', 'code and redirect_uri are required', 400);
        }

        $codeHash = hash('sha256', $code);
        $stored = $this->oauth->getByCodeHash($codeHash);
        if ($stored === null) {
            throw new OAuthException('invalid_grant', 'Invalid authorization code', 400);
        }

        if ((int) ($stored->client_id ?? 0) !== (int) $client->id) {
            throw new OAuthException('invalid_grant', 'Authorization code was not issued to this client', 400);
        }

        if (strcasecmp((string) ($stored->redirect_uri ?? ''), $redirectUri) !== 0) {
            throw new OAuthException('invalid_grant', 'redirect_uri mismatch', 400);
        }

        $expiresAt = self::parseDate((string) ($stored->expires_at ?? ''));
        if ($expiresAt !== null && $expiresAt <= new DateTimeImmutable('now')) {
            throw new OAuthException('invalid_grant', 'Authorization code expired', 400);
        }

        $this->oauth->deleteAuthCodeById((int) $stored->id);

        $scope = self::normalizeScope((string) ($stored->scope ?? ''));

        return $this->issueTokens(
            (int) $client->id,
            (int) $stored->user_id,
            $scope
        );
    }

    /**
     * Handle the refresh token grant.
     *
     * @params array $params Token request parameters.
     *
     * @return  array  Token response payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function grantRefreshToken(array $params): array
    {
        $client = $this->requireClient($params);

        $refresh = trim((string) ($params['refresh_token'] ?? ''));
        if ($refresh === '') {
            throw new OAuthException('invalid_request', 'refresh_token is required', 400);
        }

        $refreshHash = hash('sha256', $refresh);
        $stored = $this->oauth->getByRefreshTokenHash($refreshHash);
        if ($stored === null) {
            throw new OAuthException('invalid_grant', 'Invalid refresh token', 400);
        }

        if ((int) ($stored->client_id ?? 0) !== (int) $client->id) {
            throw new OAuthException('invalid_grant', 'Refresh token was not issued to this client', 400);
        }

        $scope = self::normalizeScope((string) ($stored->scope ?? ''));
        $userId = (int) ($stored->user_id ?? 0);
        if ($userId <= 0) {
            throw new OAuthException('invalid_grant', 'Refresh token is missing user', 400);
        }

        $this->oauth->deleteTokenById((int) $stored->id);

        return $this->issueTokens((int) $client->id, $userId, $scope);
    }

    /**
     * Issue a new access token pair.
     *
     * @params int $clientId Client id.
     * @params int $userId Joomla user id.
     * @params string $scope Granted scope.
     *
     * @return  array  Token response payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function issueTokens(int $clientId, int $userId, string $scope): array
    {
        $accessToken = self::generateToken(32);
        $refreshToken = self::generateToken(32);

        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $refreshToken);
        $expiresAt = (new DateTimeImmutable('now'))
            ->add(new DateInterval('PT' . self::ACCESS_TOKEN_TTL_SECONDS . 'S'));

        $this->oauth->insertToken($clientId, $userId, $accessHash, $refreshHash, $scope, $expiresAt);

        return [
            'access_token'  => $accessToken,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TOKEN_TTL_SECONDS,
            'refresh_token' => $refreshToken,
            'scope'         => $scope,
            'created_at'    => time(),
        ];
    }

    /**
     * Require a valid access token.
     *
     * @params string $token Access token.
     *
     * @return  object  Token record.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireAccessToken(string $token): object
    {
        $token = trim($token);
        if ($token === '') {
            throw new OAuthException('invalid_token', 'Missing access token', 401);
        }

        $hash = hash('sha256', $token);
        $record = $this->oauth->getByAccessTokenHash($hash);
        if ($record === null) {
            throw new OAuthException('invalid_token', 'Unknown access token', 401);
        }

        $expiresAt = self::parseDate((string) ($record->expires_at ?? ''));
        if ($expiresAt !== null && $expiresAt <= new DateTimeImmutable('now')) {
            throw new OAuthException('invalid_token', 'Access token expired', 401);
        }

        return $record;
    }

    /**
     * Require a valid client.
     *
     * @params array $params Request parameters.
     *
     * @return  object  Client record.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireClient(array $params): object
    {
        $clientId = trim((string) ($params['client_id'] ?? ''));
        $clientSecret = trim((string) ($params['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new OAuthException('invalid_client', 'client_id and client_secret are required', 401);
        }

        $client = $this->oauth->getByClientId($clientId);
        if ($client === null) {
            throw new OAuthException('invalid_client', 'Unknown client', 401);
        }

        $storedSecret = (string) ($client->client_secret ?? '');
        if ($storedSecret === '' || !hash_equals($storedSecret, $clientSecret)) {
            throw new OAuthException('invalid_client', 'Invalid client secret', 401);
        }

        return $client;
    }

    /**
     * Normalize redirect URIs from storage.
     *
     * @params string $raw Raw redirect uri payload.
     *
     * @return  string[]  Allowed redirect URIs.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeRedirectUris(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $uris = [];
            foreach ($decoded as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $uris[] = $value;
                }
            }

            return array_values(array_unique($uris));
        }

        $parts = preg_split('/\s+/', $raw) ?: [];
        $uris = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $uris[] = $part;
            }
        }

        return array_values(array_unique($uris));
    }

    /**
     * Normalize scopes.
     *
     * @params string $scope Scope string.
     *
     * @return  string  Normalized scopes.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeScope(string $scope): string
    {
        $scope = trim($scope);
        if ($scope === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $scope) ?: [];
        $unique = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $unique[$part] = true;
        }

        return implode(' ', array_keys($unique));
    }

    /**
     * Validate a redirect URI.
     *
     * @params string $uri Redirect URI.
     *
     * @return  bool  True when valid.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function isValidRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        return trim((string) ($parts['host'] ?? '')) !== '';
    }

    /**
     * Check whether a redirect URI is allowed.
     *
     * @params string $uri Redirect URI.
     * @params string[] $allowed Allowed redirect URIs.
     *
     * @return  bool  True when allowed.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function isRedirectUriAllowed(string $uri, array $allowed): bool
    {
        foreach ($allowed as $candidate) {
            if (strcasecmp(rtrim($candidate, '/'), rtrim($uri, '/')) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a date string.
     *
     * @params string $value Date string.
     *
     * @return  ?DateTimeImmutable  Parsed date or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate a random token.
     *
     * @params int $bytes Random byte length.
     *
     * @return  string  Token string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function generateToken(int $bytes): string
    {
        $bytes = $bytes > 0 ? $bytes : 32;

        return bin2hex(random_bytes($bytes));
    }
}
