<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_content_fediverse_tags
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\Content\FediverseTags\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;

/**
 * FediverseTags Class
 *
 * Replace Fediverse shortcode tags in Joomla content.
 *
 * @since  __DEPLOY_VERSION__
 */
final class FediverseTags extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * Initialize the plugin.
     *
     * Store the actor resolver used to look up local actors.
     *
     * @params ActorResolverServiceInterface $actorResolver Actor resolver service.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private readonly ActorResolverServiceInterface $actorResolver,
        $config = []
    ) {
        parent::__construct($config);
    }

    /**
     * Get subscribed Joomla events.
     *
     * Return the event map for the content preparation hook.
     *
     * @return  array  Subscribed event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Handle content preparation events.
     *
     * Replace Fediverse shortcode tags inside article text fields.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?object $article      Article object (legacy signature).
     * @params mixed  $params        Module or component params (legacy signature).
     * @params int    $page          Page number (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentPrepare(
        mixed $eventOrContext,
        ?object $article = null,
        mixed $params = null,
        int $page = 0
    ): void {
        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getContext')) {
            $context = (string) $eventOrContext->getContext();
            $article = method_exists($eventOrContext, 'getItem') ? $eventOrContext->getItem() : null;
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if ($article === null || !is_object($article)) {
            return;
        }

        if (!str_starts_with($context, 'com_content.')) {
            return;
        }

        if (!$this->articleHasTags($article)) {
            return;
        }

        $userId = isset($article->created_by) ? (int) $article->created_by : 0;
        if ($userId <= 0) {
            $this->stripTags($article);

            return;
        }

        try {
            $actor = $this->actorResolver->ensureLocalActorForUserId($userId);
        } catch (\Throwable) {
            $this->stripTags($article);

            return;
        }

        $handle   = $actor->handle;
        $uri      = $actor->uri;
        $domain   = parse_url($uri, PHP_URL_HOST) ?? '';
        $username = $actor->preferredUsername;

        $articleId = isset($article->id) ? (int) $article->id : 0;
        $objectId  = 'com_content-' . $articleId;

        $this->replaceTags($article, $handle, $uri, $domain, $username, $objectId);
    }

    /**
     * Check whether the article contains any Fediverse tags.
     *
     * Scan text fields for the {{fediverse: prefix.
     *
     * @params object $article Article object.
     *
     * @return  bool  True when at least one tag is present.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function articleHasTags(object $article): bool
    {
        foreach (['text', 'introtext', 'fulltext'] as $field) {
            if (isset($article->$field) && is_string($article->$field) && str_contains($article->$field, '{{fediverse:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip unresolved tags from article text fields.
     *
     * Replace any remaining {{fediverse:*}} tags with empty strings.
     *
     * @params object $article Article object.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function stripTags(object $article): void
    {
        foreach (['text', 'introtext', 'fulltext'] as $field) {
            if (isset($article->$field) && is_string($article->$field)) {
                $article->$field = preg_replace('/\{\{fediverse:[^}]*\}\}/', '', $article->$field) ?? $article->$field;
            }
        }
    }

    /**
     * Replace Fediverse shortcode tags in article text fields.
     *
     * Perform substitution for handle, follow, and reactions tags.
     *
     * @params object $article   Article object.
     * @params string $handle    Actor handle including the @ prefix.
     * @params string $uri       Actor URI.
     * @params string $domain    Domain extracted from the actor URI.
     * @params string $username  Actor preferred username.
     * @params string $objectId  Content object id for reactions lookup.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function replaceTags(
        object $article,
        string $handle,
        string $uri,
        string $domain,
        string $username,
        string $objectId,
    ): void {
        $handleDisplay = str_starts_with($handle, '@') ? $handle : '@' . $handle;
        $handleHtml    = '<span class="fediverse-handle">' . htmlspecialchars($handleDisplay, ENT_QUOTES, 'UTF-8') . '</span>';

        $profileUrl  = htmlspecialchars('https://' . $domain . '/@' . $username, ENT_QUOTES, 'UTF-8');
        $handleEsc   = htmlspecialchars($handleDisplay, ENT_QUOTES, 'UTF-8');
        $followHtml  = '<div class="fediverse-follow-widget">'
            . '<span class="fediverse-follow-handle">' . $handleEsc . '</span>'
            . '<button class="fediverse-follow-copy" data-handle="' . $handleEsc . '"'
            . ' onclick="navigator.clipboard.writeText(this.dataset.handle)">Copy handle</button>'
            . '<a class="fediverse-follow-link" href="' . $profileUrl . '">View profile</a>'
            . '</div>';

        $reactionsHtml = $this->buildReactionsHtml($objectId);

        foreach (['text', 'introtext', 'fulltext'] as $field) {
            if (!isset($article->$field) || !is_string($article->$field)) {
                continue;
            }

            $article->$field = str_replace('{{fediverse:handle}}', $handleHtml, $article->$field);
            $article->$field = str_replace('{{fediverse:follow}}', $followHtml, $article->$field);
            $article->$field = str_replace('{{fediverse:reactions}}', $reactionsHtml, $article->$field);
        }
    }

    /**
     * Build an inline reactions HTML string.
     *
     * Query reaction counts and return a formatted span element.
     *
     * @params string $objectId Content object id.
     *
     * @return  string  HTML string with reaction counts.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildReactionsHtml(string $objectId): string
    {
        try {
            $db = Factory::getContainer()->get('db');
        } catch (\Throwable) {
            return '';
        }

        try {
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
                'SUM(CASE WHEN %s = %s THEN 1 ELSE 0 END)',
                $typeField,
                $db->quote('Create')
            );

            $query = $db->createQuery()
                ->select([$likesExpr . ' AS likes', $boostExpr . ' AS boosts', $replyExpr . ' AS replies'])
                ->from($db->quoteName('#__fediverse_inbound_activities'))
                ->where($db->quoteName('object_id') . ' = :objectId')
                ->bind(':objectId', $objectId, ParameterType::STRING)
                ->setLimit(1);

            $db->setQuery($query);
            $row = $db->loadAssoc() ?: [];
        } catch (\Throwable) {
            return '';
        }

        $likes   = (int) ($row['likes'] ?? 0);
        $boosts  = (int) ($row['boosts'] ?? 0);
        $replies = (int) ($row['replies'] ?? 0);

        return '<span class="fediverse-reactions-inline">'
            . '&#9829; ' . $likes . ' &middot; '
            . '&#8635; ' . $boosts . ' &middot; '
            . '&#128172; ' . $replies
            . '</span>';
    }
}
