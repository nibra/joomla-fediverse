<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      mod_fediverse_reactions
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Module\Fediverse\Reactions\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Input\Input;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use NX\Component\Fediverse\Administrator\Service\Comments\CommentRendererRegistry;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;

/**
 * ReactionsDataProvider Class
 *
 * Provide helper behavior for rendering inbound replies and reactions.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ReactionsDataProvider
{
    /**
     * Get reactions data for the module.
     *
     * @params Registry $params Module parameters.
     *
     * @return  array<string,mixed>  Reactions data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getReactionsData(Registry $params): array
    {
        $target = self::resolveTargetContext($params);
        if ($target === null) {
            return [
                'available' => false,
                'reason'    => 'object_not_resolved',
            ];
        }

        $objectId = $target['object_id'];
        $context  = $target['context'];
        $itemId   = $target['item_id'];

        $showReactions = (int) $params->get('show_reactions', 1) === 1;
        $showReplies   = (int) $params->get('show_replies', 1) === 1;
        $replyLimit    = max(0, (int) $params->get('reply_limit', 10));

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $counts  = self::fetchCounts($db, $objectId);
        $replies = $showReplies && $replyLimit > 0 ? self::fetchReplies($db, $objectId, $replyLimit) : [];
        $renderedComments = $showReplies
            ? self::renderRepliesWithRegisteredRenderer(
                self::resolveCommentRendererRegistry(),
                $context,
                $itemId,
                $objectId,
                $counts,
                $replies
            )
            : '';

        return [
            'available'      => true,
            'object_id'      => $objectId,
            'counts'         => $counts,
            'replies'         => $replies,
            'rendered_comments' => $renderedComments,
            'show_reactions' => $showReactions,
            'show_replies'   => $showReplies,
            'post_url'       => Uri::current(),
        ];
    }

    /**
     * Resolve object id and context metadata for the current module target.
     *
     * @params Registry $params Module parameters.
     *
     * @return  ?array{object_id:string,context:string,item_id:?int}  Target metadata or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveTargetContext(Registry $params): ?array
    {
        $source = trim((string) $params->get('source', 'current'));
        if ($source === 'object_id') {
            $objectId = trim((string) $params->get('object_id', ''));

            return $objectId !== '' ? ['object_id' => $objectId, 'context' => '', 'item_id' => null] : null;
        }

        if ($source !== 'current') {
            return null;
        }

        $input   = Factory::getApplication()->getInput();
        $option  = trim((string) $input->getCmd('option', ''));
        $view    = trim((string) $input->getCmd('view', ''));
        $context = $option !== '' && $view !== '' ? $option . '.' . $view : '';

        if ($context === '') {
            return null;
        }

        $itemId = self::resolveItemIdFromInput($input);
        if ($itemId === null) {
            return null;
        }

        $registry = Factory::getContainer()->get(ContentProviderRegistry::class);
        $objectId = self::resolveObjectIdForContext($context, $itemId, $registry);
        if ($objectId === null) {
            return null;
        }

        return [
            'object_id' => $objectId,
            'context'   => $context,
            'item_id'   => $itemId,
        ];
    }

    /**
     * Resolve the object id for a context and item id.
     *
     * @params string $context Content context.
     * @params ?int $itemId Content item id.
     * @params ContentProviderRegistry $registry Provider registry.
     *
     * @return  ?string  Object id or null when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function resolveObjectIdForContext(
        string $context,
        ?int $itemId,
        ContentProviderRegistry $registry
    ): ?string {
        if ($itemId === null || $itemId <= 0) {
            return null;
        }

        $provider = $registry->getProviderForContext($context);
        if ($provider === null) {
            return null;
        }

        return $provider->getKey() . '-' . $itemId;
    }

    /**
     * Resolve the registered comments renderer registry from the container.
     *
     * @return  ?CommentRendererRegistry  Registry instance or null when unavailable.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveCommentRendererRegistry(): ?CommentRendererRegistry
    {
        try {
            return Factory::getContainer()->get(CommentRendererRegistry::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Render replies with the first registered comments renderer for the target.
     *
     * @params ?CommentRendererRegistry $registry Comments renderer registry.
     * @params string $context Joomla context.
     * @params ?int $itemId Joomla item id.
     * @params string $objectId Fediverse object id.
     * @params array<string,int> $counts Reaction counts.
     * @params array<int,array<string,string>> $replies Reply rows.
     *
     * @return  string  Rendered HTML or empty string when no renderer applies.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function renderRepliesWithRegisteredRenderer(
        ?CommentRendererRegistry $registry,
        string $context,
        ?int $itemId,
        string $objectId,
        array $counts,
        array $replies
    ): string {
        if ($registry === null) {
            return '';
        }

        $renderer = $registry->getRendererForTarget($context, $itemId, $objectId);
        if ($renderer === null) {
            return '';
        }

        return trim($renderer->render($context, $itemId, $objectId, $counts, $replies));
    }

    /**
     * Resolve the current item id from input.
     *
     * @params Input $input Joomla input.
     *
     * @return  ?int  Item id or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveItemIdFromInput(Input $input): ?int
    {
        $raw = trim((string) $input->getString('id', ''));
        if ($raw === '') {
            $raw = trim((string) $input->getString('item_id', ''));
        }
        if ($raw === '') {
            $raw = trim((string) $input->getString('a_id', ''));
        }

        return self::parseItemId($raw);
    }

    /**
     * Parse a Joomla item id from a raw input string.
     *
     * @params string $raw Raw id string.
     *
     * @return  ?int  Parsed id or null when invalid.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function parseItemId(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parts = explode(':', $raw, 2);
        $id    = (int) $parts[0];

        return $id > 0 ? $id : null;
    }

    /**
     * Fetch reaction counts for an object.
     *
     * @params DatabaseInterface $db Database connection.
     * @params string $objectId Object id segment.
     *
     * @return  array<string,int>  Counts keyed by type.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function fetchCounts(DatabaseInterface $db, string $objectId): array
    {
        $typeField = $db->quoteName('activity_type');
        $likesExpr = sprintf(
            'SUM(CASE WHEN %s IN (%s, %s) THEN 1 ELSE 0 END)',
            $typeField,
            $db->quote('Like'),
            $db->quote('Add')
        );
        $boostExpr = sprintf(
            'SUM(CASE WHEN %s = %s THEN 1 ELSE 0 END)',
            $typeField,
            $db->quote('Announce')
        );
        $replyExpr = sprintf(
            'SUM(CASE WHEN %s = %s AND %s = %s THEN 1 ELSE 0 END)',
            $typeField,
            $db->quote('Create'),
            $db->quoteName('c.state'),
            $db->quote('approved')
        );

        $query = $db->createQuery()
            ->select([
                $likesExpr . ' AS likes',
                $boostExpr . ' AS boosts',
                $replyExpr . ' AS replies',
            ])
            ->from($db->quoteName('#__fediverse_inbound_activities', 'ia'))
            ->innerJoin(
                $db->quoteName('#__fediverse_inbox', 'i')
                . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('ia.inbox_id')
            )
            ->leftJoin(
                $db->quoteName('#__fediverse_comments', 'c')
                . ' ON ' . $db->quoteName('c.inbound_activity_id') . ' = ' . $db->quoteName('ia.id')
            )
            ->where($db->quoteName('ia.object_id') . ' = :objectId')
            ->where($db->quoteName('i.status') . ' = ' . $db->quote('processed'))
            ->bind(':objectId', $objectId, ParameterType::STRING)
            ->setLimit(1);

        $db->setQuery($query);
        $row = $db->loadAssoc() ?: [];

        return [
            'likes'   => (int) ($row['likes'] ?? 0),
            'boosts'  => (int) ($row['boosts'] ?? 0),
            'replies' => (int) ($row['replies'] ?? 0),
        ];
    }

    /**
     * Fetch replies for an object.
     *
     * @params DatabaseInterface $db Database connection.
     * @params string $objectId Object id segment.
     * @params int $limit Maximum replies to return.
     *
     * @return  array<int,array<string,string>>  Replies list.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function fetchReplies(DatabaseInterface $db, string $objectId, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $query = $db->createQuery()
            ->select([
                $db->quoteName('ia.raw_json'),
                $db->quoteName('ia.created_at'),
                $db->quoteName('ra.handle'),
                $db->quoteName('ra.uri'),
                $db->quoteName('ra.preferred_username'),
            ])
            ->from($db->quoteName('#__fediverse_inbound_activities', 'ia'))
            ->innerJoin(
                $db->quoteName('#__fediverse_inbox', 'i')
                . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('ia.inbox_id')
            )
            ->innerJoin(
                $db->quoteName('#__fediverse_comments', 'c')
                . ' ON ' . $db->quoteName('c.inbound_activity_id') . ' = ' . $db->quoteName('ia.id')
            )
            ->leftJoin(
                $db->quoteName('#__fediverse_actors', 'ra')
                . ' ON ' . $db->quoteName('ra.id') . ' = ' . $db->quoteName('ia.remote_actor_id')
            )
            ->where($db->quoteName('ia.object_id') . ' = :objectId')
            ->where($db->quoteName('ia.activity_type') . ' = ' . $db->quote('Create'))
            ->where($db->quoteName('c.state') . ' = ' . $db->quote('approved'))
            ->where($db->quoteName('i.status') . ' = ' . $db->quote('processed'))
            ->order($db->quoteName('ia.created_at') . ' DESC')
            ->bind(':objectId', $objectId, ParameterType::STRING)
            ->setLimit($limit);

        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];

        $replies = [];
        foreach ($rows as $row) {
            $rawJson = (string) ($row['raw_json'] ?? '');
            $activity = json_decode($rawJson, true);
            if (!is_array($activity)) {
                continue;
            }

            $content = self::extractReplyContent($activity);
            if ($content === '') {
                continue;
            }

            $actorLabel = self::resolveActorLabel($row, $activity);
            $actorUrl   = self::resolveActorUrl($row, $activity);

            $replies[] = [
                'actor_label' => $actorLabel,
                'actor_url'   => $actorUrl,
                'content'     => $content,
                'created_at'  => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $replies;
    }

    /**
     * Extract reply content from a Create activity.
     *
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Sanitized reply content.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function extractReplyContent(array $activity): string
    {
        $object = $activity['object'] ?? null;
        if (!is_array($object)) {
            return '';
        }

        $content = '';
        if (isset($object['content']) && is_string($object['content'])) {
            $content = $object['content'];
        } elseif (isset($object['contentMap']) && is_array($object['contentMap'])) {
            foreach ($object['contentMap'] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $content = $value;
                    break;
                }
            }
        }

        $content = trim(strip_tags($content));

        return $content;
    }

    /**
     * Resolve an actor label for display.
     *
     * @params array<string,mixed> $row Database row.
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Display label.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveActorLabel(array $row, array $activity): string
    {
        $handle = trim((string) ($row['handle'] ?? ''));
        if ($handle !== '') {
            return str_starts_with($handle, '@') ? $handle : '@' . $handle;
        }

        $preferred = '';
        $actor = $activity['actor'] ?? null;
        if (is_array($actor)) {
            $preferred = trim((string) ($actor['preferredUsername'] ?? ''));
            if ($preferred === '') {
                $preferred = trim((string) ($actor['name'] ?? ''));
            }
        }

        if ($preferred !== '') {
            return str_starts_with($preferred, '@') ? $preferred : '@' . $preferred;
        }

        $actorUrl = self::resolveActorUrl($row, $activity);
        if ($actorUrl !== '') {
            return $actorUrl;
        }

        return Text::_('MOD_FEDIVERSE_REACTIONS_ACTOR_UNKNOWN');
    }

    /**
     * Resolve an actor URL for display.
     *
     * @params array<string,mixed> $row Database row.
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Actor URL or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveActorUrl(array $row, array $activity): string
    {
        $uri = trim((string) ($row['uri'] ?? ''));
        if ($uri !== '') {
            return $uri;
        }

        $actor = $activity['actor'] ?? null;
        if (is_string($actor) && $actor !== '') {
            return $actor;
        }

        if (is_array($actor) && isset($actor['id']) && is_string($actor['id'])) {
            return $actor['id'];
        }

        return '';
    }
}
