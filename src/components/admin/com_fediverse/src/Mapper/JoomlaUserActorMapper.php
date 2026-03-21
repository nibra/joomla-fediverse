<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Mapper;

use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;

/**
 * JoomlaUserActorMapper Class
 *
 * Map Joomla User Actor data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaUserActorMapper
{
    /**
     * Initialize the actor mapper.
     *
     * Store the component configuration for handle formatting.
     *
     * @params ?FediverseConfig $config Component configuration.
     *
     * @return  void None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private ?FediverseConfig $config = null)
    {
        $this->config = $config ?? new FediverseConfig();
    }

    /**
     * Build a stable handle for a user id.
     *
     * Return a deterministic handle for local users.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  string  Actor handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function handleForUserId(int $userId): string
    {
        return $this->config->formatActorHandle($userId, '');
    }

    /**
     * Normalize a base URL.
     *
     * Trim trailing slashes to ensure consistent URL building.
     *
     * @params string $baseUrl Base URL to normalize.
     *
     * @return  string  Normalized base URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/');
    }

    /**
     * Build an actor URI.
     *
     * Construct the canonical actor URL for a handle.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $handle Actor handle.
     *
     * @return  string  Actor URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function actorUri(string $baseUrl, string $handle): string
    {
        return $this->buildInternalUrl($baseUrl, '/ap/actors/' . rawurlencode($handle));
    }

    /**
     * Build an inbox URL.
     *
     * Construct the actor inbox endpoint for a handle.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $handle Actor handle.
     *
     * @return  string  Inbox URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function inboxUrl(string $baseUrl, string $handle): string
    {
        return $this->actorUri($baseUrl, $handle) . '/inbox';
    }

    /**
     * Build an outbox URL.
     *
     * Construct the actor outbox endpoint for a handle.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $handle Actor handle.
     *
     * @return  string  Outbox URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function outboxUrl(string $baseUrl, string $handle): string
    {
        return $this->actorUri($baseUrl, $handle) . '/outbox';
    }

    /**
     * Build a shared inbox URL.
     *
     * Construct the shared inbox endpoint for the site.
     *
     * @params string $baseUrl Base URL for building links.
     *
     * @return  string  Shared inbox URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function sharedInboxUrl(string $baseUrl): string
    {
        return $this->buildInternalUrl($baseUrl, '/ap/inbox');
    }

    /**
     * Build a key id URI.
     *
     * Append the key fragment identifier to the actor URI.
     *
     * @params string $actorUri Actor URI.
     *
     * @return  string  Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function keyIdUri(string $actorUri): string
    {
        return $actorUri . '#main-key';
    }

    /**
     * Build an object URI for an article.
     *
     * Construct the ActivityPub object URI for a Joomla article.
     *
     * @params string $baseUrl Base URL for building links.
     * @params int $articleId Joomla article identifier.
     *
     * @return  string  Object URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function objectUriForArticle(string $baseUrl, int $articleId): string
    {
        return $this->objectUriForContent($baseUrl, 'article', $articleId);
    }

    /**
     * Build an object URI for a content item.
     *
     * Construct the ActivityPub object URI for a content provider key.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $providerKey Content provider key.
     * @params int $itemId Content item identifier.
     *
     * @return  string  Object URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function objectUriForContent(string $baseUrl, string $providerKey, int $itemId): string
    {
        $key = trim($providerKey);
        if ($key === '') {
            $key = 'object';
        }

        return $this->buildInternalUrl($baseUrl, '/ap/objects/' . rawurlencode($key) . '-' . $itemId);
    }

    /**
     * Build an activity URI.
     *
     * Construct the ActivityPub activity URI using a token.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $token Activity token.
     *
     * @return  string  Activity URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function activityUri(string $baseUrl, string $token): string
    {
        return $this->buildInternalUrl($baseUrl, '/ap/activities/' . rawurlencode($token));
    }

    /**
     * Build a base URI instance for local URL construction.
     *
     * Normalize base URL and clear query/fragment.
     *
     * @params string $baseUrl Base URL for building links.
     *
     * @return  Uri  Base URI instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildBaseUri(string $baseUrl): Uri
    {
        $uri = clone Uri::getInstance($baseUrl);
        $uri->setQuery('');
        $uri->setFragment('');
        $path = (string) $uri->getPath();
        $uri->setPath(rtrim($path, '/'));

        return $uri;
    }

    /**
     * Build an internal URL from a base URL and path.
     *
     * Preserve any base path while appending the provided path.
     *
     * @params string $baseUrl Base URL for building links.
     * @params string $path Path suffix to append.
     *
     * @return  string  Absolute URL string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildInternalUrl(string $baseUrl, string $path): string
    {
        $uri = $this->buildBaseUri($baseUrl);
        $basePath = rtrim((string) $uri->getPath(), '/');
        $suffix = '/' . ltrim($path, '/');

        $uri->setPath($basePath . $suffix);

        return $uri->toString();
    }

    /**
     * Map a local user to an actor.
     *
     * Build a local actor instance for a Joomla user.
     *
     * @params int $userId Joomla user identifier.
     * @params string $preferredUsername Public-facing username.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  Actor  Local actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function mapLocalActor(int $userId, string $preferredUsername, string $baseUrl): Actor
    {
        $handle   = $this->config->formatActorHandle($userId, $preferredUsername);
        $actorUri = $this->actorUri($baseUrl, $handle);

        return Actor::newLocal(
            userId: $userId,
            handle: $handle,
            preferredUsername: $preferredUsername,
            uri: $actorUri,
            inboxUrl: $this->inboxUrl($baseUrl, $handle),
            outboxUrl: $this->outboxUrl($baseUrl, $handle),
            sharedInboxUrl: $this->sharedInboxUrl($baseUrl),
        );
    }
}
