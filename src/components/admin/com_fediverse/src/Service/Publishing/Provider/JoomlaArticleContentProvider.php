<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Publishing\Provider;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaContentMapperInterface;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderInterface;

/**
 * JoomlaArticleContentProvider Class
 *
 * Provide ActivityPub mappings for Joomla articles.
 *
 * @since  __DEPLOY_VERSION__
 */
final class JoomlaArticleContentProvider implements ContentProviderInterface
{
    /**
     * Initialize the provider.
     *
     * Store mapper and database dependencies.
     *
     * @params JoomlaContentMapperInterface $mapper Article mapper.
     * @params DatabaseInterface $db Database connection.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private JoomlaContentMapperInterface $mapper,
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
        return 'article';
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

        if ($context === 'com_content.article') {
            return true;
        }

        return str_starts_with($context, 'com_content.');
    }

    /**
     * Resolve the user id for an article item.
     *
     * @params object $item Joomla content item.
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
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $itemId, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $result = $this->db->loadResult();

        return (int) ($result ?? 0);
    }

    /**
     * Parse an object identifier into an article id.
     *
     * @params string $objectId Object identifier segment.
     *
     * @return  ?int  Article id or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function parseObjectId(string $objectId): ?int
    {
        if (!preg_match('/^article-(\d+)$/', $objectId, $matches)) {
            return null;
        }

        $articleId = (int) $matches[1];
        if ($articleId <= 0) {
            return null;
        }

        return $articleId;
    }

    /**
     * Fetch an article by id.
     *
     * @params int $id Article id.
     *
     * @return  ?object  Article row or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchById(int $id): ?object
    {
        $aid = $id;

        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('introtext'),
                $this->db->quoteName('fulltext'),
                $this->db->quoteName('attribs'),
                $this->db->quoteName('images'),
                $this->db->quoteName('catid'),
                $this->db->quoteName('created'),
                $this->db->quoteName('modified'),
                $this->db->quoteName('created_by'),
            ])
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $aid, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        if ($row) {
            $tagQuery = $this->db->createQuery()
                ->select([
                    $this->db->quoteName('tags.title'),
                    $this->db->quoteName('tags.alias'),
                ])
                ->from($this->db->quoteName('#__contentitem_tag_map', 'map'))
                ->join(
                    'INNER',
                    $this->db->quoteName('#__tags', 'tags')
                    . ' ON ' . $this->db->quoteName('tags.id')
                    . ' = ' . $this->db->quoteName('map.tag_id')
                )
                ->where($this->db->quoteName('map.content_item_id') . ' = :id')
                ->where($this->db->quoteName('map.type_alias') . ' = :typeAlias')
                ->bind(':id', $aid, ParameterType::INTEGER)
                ->bind(':typeAlias', 'com_content.article', ParameterType::STRING);

            $this->db->setQuery($tagQuery);
            $row->tags = $this->db->loadObjectList();
        }

        return $row ?: null;
    }

    /**
     * Map an article to a create or update activity.
     *
     * @params Actor $actor Local actor.
     * @params object $item Article item.
     * @params bool $isNew Whether the item is new.
     * @params string $baseUrl Base URL.
     *
     * @return  ActivityEnvelope  Activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toCreateOrUpdate(Actor $actor, object $item, bool $isNew, string $baseUrl): ActivityEnvelope
    {
        return $this->mapper->toCreateOrUpdate($actor, $item, $isNew, $baseUrl);
    }

    /**
     * Map an article to a delete activity.
     *
     * @params Actor $actor Local actor.
     * @params object $item Article item.
     * @params string $baseUrl Base URL.
     *
     * @return  ActivityEnvelope  Activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toDelete(Actor $actor, object $item, string $baseUrl): ActivityEnvelope
    {
        return $this->mapper->toDelete($actor, $item, $baseUrl);
    }

    /**
     * Map an article to an ActivityPub object.
     *
     * @params Actor $actor Local actor.
     * @params object $item Article item.
     * @params string $baseUrl Base URL.
     *
     * @return  array<string,mixed> ActivityPub object payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function toObject(Actor $actor, object $item, string $baseUrl): array
    {
        return $this->mapper->toObject($actor, $item, $baseUrl);
    }
}
