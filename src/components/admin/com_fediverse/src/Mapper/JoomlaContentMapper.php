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
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;

/**
 * JoomlaContentMapper Class
 *
 * Map Joomla Content data.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaContentMapper implements JoomlaContentMapperInterface
{
    private const PUBLIC_AUDIENCE = 'https://www.w3.org/ns/activitystreams#Public';

    /**
     * Initialize the content mapper.
     *
     * Store the actor mapper dependency used for URL generation.
     *
     * @params JoomlaUserActorMapper $actorMapper Actor mapper.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private JoomlaUserActorMapper $actorMapper)
    {
    }

    /**
     * Map an article to a create or update activity.
     *
     * Build an ActivityPub activity for article creation or update.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params bool $isNew Whether the article is new.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for create or update.
     * @throws  \Exception  if token generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $article, bool $isNew, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $object     = $this->toObject($actor, $article, $baseUrl);
        $audience   = $this->buildAudience($actor);

        $json = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => $isNew ? 'Create' : 'Update',
            'actor'    => $actor->uri,
            'object'   => $object,
            'to'       => $audience['to'],
            'cc'       => $audience['cc'],
        ];

        return ActivityEnvelope::fromJson($json);
    }

    /**
     * Map an article to a delete activity.
     *
     * Build an ActivityPub delete activity for the article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  ActivityEnvelope  Activity envelope for delete.
     * @throws  \Exception  if token generation fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $article, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $objectId   = $this->actorMapper->objectUriForArticle($baseUrl, (int) $article->id);
        $audience   = $this->buildAudience($actor);

        $json = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => 'Delete',
            'actor'    => $actor->uri,
            'object'   => [
                'id'   => $objectId,
                'type' => 'Tombstone',
            ],
            'to'       => $audience['to'],
            'cc'       => $audience['cc'],
        ];

        return ActivityEnvelope::fromJson($json);
    }

    /**
     * Map an article to an ActivityPub object.
     *
     * Build an ActivityPub object representation for a Joomla article.
     *
     * @params Actor $actor Local actor performing the action.
     * @params object $article Joomla article object.
     * @params string $baseUrl Base URL for building links.
     *
     * @return  array<string,mixed>  ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $article, string $baseUrl): array
    {
        $objectId  = $this->actorMapper->objectUriForArticle($baseUrl, (int) $article->id);
        $audience  = $this->buildAudience($actor);
        $published = $this->formatTimestamp($article->created ?? null);
        $updated   = $this->formatTimestamp($article->modified ?? null);

        $object = [
            'id'           => $objectId,
            'type'         => $actor->objectType ?? 'Note',
            'attributedTo' => $actor->uri,
            'name'         => (string) ($article->title ?? ''),
            'content'      => $this->resolveArticleContent($article),
            'url'          => $this->articleUrl($baseUrl, (int) $article->id),
            'to'           => $audience['to'],
            'cc'           => $audience['cc'],
        ];

        if ($published !== null) {
            $object['published'] = $published;
        }

        if ($updated !== null) {
            $object['updated'] = $updated;
        }

        return $object;
    }

    /**
     * Resolve the best article content field.
     *
     * Prefer the composed text field, falling back to intro/full text.
     *
     * @params object $article Joomla article object.
     *
     * @return  string  Content string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveArticleContent(object $article): string
    {
        $intro = is_string($article->introtext ?? null) ? (string) $article->introtext : '';
        $full  = is_string($article->fulltext ?? null) ? (string) $article->fulltext : '';

        if ($intro !== '' && $full !== '') {
            return $intro . "\n\n" . $full;
        }

        if ($intro !== '') {
            return $intro;
        }

        if ($full !== '') {
            return $full;
        }

        $text = $article->text ?? null;
        if (is_string($text) && trim($text) !== '') {
            return $text;
        }

        return '';
    }

    /**
     * Format a Joomla datetime string as ISO 8601.
     *
     * Convert common Joomla datetime values to an ActivityPub-compatible format.
     *
     * @params mixed $value Datetime string or null.
     *
     * @return  ?string  ISO 8601 timestamp or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function formatTimestamp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $dt = new \DateTimeImmutable($trimmed);
        } catch (\Throwable) {
            return null;
        }

        return $dt->format(\DateTimeInterface::ATOM);
    }

    /**
     * Build ActivityPub audience fields.
     *
     * Emit Public + followers targets for standard delivery.
     *
     * @params Actor $actor Local actor.
     *
     * @return  array{to:array<int,string>,cc:array<int,string>}  Audience map.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildAudience(Actor $actor): array
    {
        $followers = rtrim($actor->uri, '/') . '/followers';

        return [
            'to' => [self::PUBLIC_AUDIENCE],
            'cc' => [$followers],
        ];
    }

    /**
     * Build a public article URL.
     *
     * Construct the article URL from the base site URL and article id.
     *
     * @params string $baseUrl Base URL for building links.
     * @params int $articleId Joomla article identifier.
     *
     * @return  string  Article URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function articleUrl(string $baseUrl, int $articleId): string
    {
        $uri = clone Uri::getInstance($baseUrl);
        $uri->setQuery('');
        $uri->setFragment('');

        $basePath = rtrim((string) $uri->getPath(), '/');
        $uri->setPath($basePath . '/index.php');
        $uri->setQuery([
            'option' => 'com_content',
            'view'   => 'article',
            'id'     => $articleId,
        ]);

        return $uri->toString();
    }
}
