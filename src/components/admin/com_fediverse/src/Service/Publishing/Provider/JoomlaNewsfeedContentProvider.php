<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Publishing\Provider;

use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderInterface;

/**
 * JoomlaNewsfeedContentProvider Class
 *
 * Provide ActivityPub mappings for Joomla newsfeeds.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaNewsfeedContentProvider implements ContentProviderInterface
{
    private const PUBLIC_AUDIENCE = 'https://www.w3.org/ns/activitystreams#Public';

    /**
     * Initialize the provider.
     *
     * Store mapper and database dependencies.
     *
     * @params JoomlaUserActorMapper $actorMapper Actor mapper.
     * @params DatabaseInterface $db Database connection.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private JoomlaUserActorMapper $actorMapper,
        private DatabaseInterface $db,
    ) {
    }

    /**
     * Get the provider key.
     *
     * @return  string  Provider key.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getKey(): string
    {
        return 'newsfeed';
    }

    /**
     * Check if the provider supports a context.
     *
     * @params string $context Joomla content context.
     *
     * @return  bool  True when supported.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function supportsContext(string $context): bool
    {
        $context = trim($context);
        if ($context === '') {
            return false;
        }

        return $context === 'com_newsfeeds.newsfeed';
    }

    /**
     * Resolve the user id for a newsfeed item.
     *
     * @params object $item Joomla newsfeed item.
     * @params string $context Joomla content context.
     *
     * @return  int  User id or 0 when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resolveUserId(object $item, string $context): int
    {
        $userId = (int) ($item->created_by ?? 0);
        if ($userId > 0) {
            return $userId;
        }

        $itemId = (int) ($item->id ?? 0);
        if ($itemId <= 0) {
            return 0;
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName('created_by'))
            ->from($this->db->quoteName('#__newsfeeds'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $itemId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $result = $this->db->loadResult();

        return (int) ($result ?? 0);
    }

    /**
     * Parse an object identifier into a newsfeed id.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  ?int  Newsfeed id or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function parseObjectId(string $objectId): ?int
    {
        if (!preg_match('/^newsfeed-(\d+)$/', $objectId, $matches)) {
            return null;
        }

        $newsfeedId = (int) $matches[1];
        if ($newsfeedId <= 0) {
            return null;
        }

        return $newsfeedId;
    }

    /**
     * Fetch a newsfeed by id.
     *
     * @params int $id Newsfeed id.
     *
     * @return  ?object  Newsfeed row or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchById(int $id): ?object
    {
        $nid = $id;

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('name'),
                $this->db->quoteName('description'),
                $this->db->quoteName('link'),
                $this->db->quoteName('created'),
                $this->db->quoteName('modified'),
                $this->db->quoteName('created_by'),
            ])
            ->from($this->db->quoteName('#__newsfeeds'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $nid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Map a newsfeed to a create or update activity.
     *
     * @params Actor $actor Local actor.
     * @params object $item Newsfeed item.
     * @params bool $isNew Whether the item is new.
     * @params string $baseUrl Base URL.
     *
     * @return  ActivityEnvelope  Activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $item, bool $isNew, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $object     = $this->toObject($actor, $item, $baseUrl);
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
     * Map a newsfeed to a delete activity.
     *
     * @params Actor $actor Local actor.
     * @params object $item Newsfeed item.
     * @params string $baseUrl Base URL.
     *
     * @return  ActivityEnvelope  Activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $item, string $baseUrl): ActivityEnvelope
    {
        $token      = bin2hex(random_bytes(16));
        $activityId = $this->actorMapper->activityUri($baseUrl, $token);
        $objectId   = $this->actorMapper->objectUriForContent($baseUrl, $this->getKey(), (int) $item->id);
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
     * Map a newsfeed to an ActivityPub object.
     *
     * @params Actor $actor Local actor.
     * @params object $item Newsfeed item.
     * @params string $baseUrl Base URL.
     *
     * @return  array<string,mixed> ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $item, string $baseUrl): array
    {
        $objectId  = $this->actorMapper->objectUriForContent($baseUrl, $this->getKey(), (int) $item->id);
        $audience  = $this->buildAudience($actor);
        $published = $this->formatTimestamp($item->created ?? null);
        $updated   = $this->formatTimestamp($item->modified ?? null);

        $object = [
            'id'           => $objectId,
            'type'         => 'Note',
            'attributedTo' => $actor->uri,
            'name'         => (string) ($item->name ?? $item->title ?? ''),
            'content'      => $this->resolveNewsfeedDescription($item),
            'url'          => $this->newsfeedUrl($baseUrl, (int) $item->id),
            'to'           => $audience['to'],
            'cc'           => $audience['cc'],
        ];

        $link = trim((string) ($item->link ?? ''));
        if ($link !== '') {
            $object['attachment'] = [[
                'type' => 'Link',
                'href' => $link,
            ]];
        }

        if ($published !== null) {
            $object['published'] = $published;
        }

        if ($updated !== null) {
            $object['updated'] = $updated;
        }

        return $object;
    }

    /**
     * Resolve the best newsfeed description field.
     *
     * Prefer description, falling back to name.
     *
     * @params object $item Joomla newsfeed item.
     *
     * @return  string  Description string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveNewsfeedDescription(object $item): string
    {
        $description = $item->description ?? null;
        if (is_string($description) && trim($description) !== '') {
            return $description;
        }

        $name = $item->name ?? $item->title ?? null;
        if (is_string($name) && trim($name) !== '') {
            return $name;
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
     * Build a public newsfeed URL.
     *
     * Construct the newsfeed URL from the base site URL and item id.
     *
     * @params string $baseUrl Base URL for building links.
     * @params int $newsfeedId Joomla newsfeed identifier.
     *
     * @return  string  Newsfeed URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function newsfeedUrl(string $baseUrl, int $newsfeedId): string
    {
        $uri = clone Uri::getInstance($baseUrl);
        $uri->setQuery('');
        $uri->setFragment('');

        $basePath = rtrim((string) $uri->getPath(), '/');
        $uri->setPath($basePath . '/index.php');
        $uri->setQuery([
            'option' => 'com_newsfeeds',
            'view'   => 'newsfeed',
            'id'     => $newsfeedId,
        ]);

        return $uri->toString();
    }
}
