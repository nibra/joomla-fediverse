<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_content_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\Content\Fediverse\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;
use NX\Component\Fediverse\Administrator\Service\Publishing\PublishService;

/**
 * Fediverse Class
 *
 * Provide the Fediverse content plugin.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Fediverse extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * Initialize the content plugin.
     *
     * Store the publish service used to emit ActivityPub activities.
     *
     * @params PublishService $publishService Publish service.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private readonly PublishService $publishService, $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Get subscribed Joomla events.
     *
     * Return the event map for content lifecycle hooks.
     *
     * @return  array  Subscribed event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave'   => 'onContentAfterSave',
            'onContentAfterDelete' => 'onContentAfterDelete',
            'onContentChangeState' => 'onContentChangeState',
        ];
    }

    /**
     * Handle content save events.
     *
     * Publish article create or update activities when applicable.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?object $article Joomla article object (legacy signature).
     * @params ?bool $isNew Whether the article is new (legacy signature).
     * @params array $data Additional event data (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterSave(mixed $eventOrContext, ?object $article = null, ?bool $isNew = null, array $data = []): void
    {
        if ($eventOrContext instanceof AfterSaveEvent) {
            $context = $eventOrContext->getContext();
            $article = $eventOrContext->getItem();
            $isNew   = $eventOrContext->getIsNew();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if ($article === null) {
            return;
        }

        if (is_array($article)) {
            $article = (object) $article;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        $isNewFlag = $isNew ?? false;

        $state = null;
        if (isset($article->state)) {
            $state = (int) $article->state;
        } elseif (isset($article->published)) {
            $state = (int) $article->published;
        }
        if ($state !== null && $state <= 0) {
            if ($isNewFlag) {
                return;
            }

            $this->publishService->onContentDeleted($context, $article);

            return;
        }

        $this->publishService->onContentSaved($context, $article, (bool) $isNewFlag);
    }

    /**
     * Handle content delete events.
     *
     * Publish article delete activities when applicable.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?object $article Joomla article object (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterDelete(mixed $eventOrContext, ?object $article = null): void
    {
        if ($eventOrContext instanceof AfterDeleteEvent) {
            $context = $eventOrContext->getContext();
            $article = $eventOrContext->getItem();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if ($article === null) {
            return;
        }

        if (is_array($article)) {
            $article = (object) $article;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        $this->publishService->onContentDeleted($context, $article);
    }

    /**
     * Handle content state changes.
     *
     * Publish delete activities when articles are unpublished or trashed.
     *
     * @params mixed $eventOrContext Event object or legacy context string.
     * @params ?array $pks Article ids (legacy signature).
     * @params ?int $value New state value (legacy signature).
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentChangeState(mixed $eventOrContext, ?array $pks = null, ?int $value = null): void
    {
        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getContext')) {
            $context = (string) $eventOrContext->getContext();
        } else {
            $context = is_string($eventOrContext) ? $eventOrContext : '';
        }

        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getPks')) {
            $pks = $eventOrContext->getPks();
        }

        if (is_object($eventOrContext) && method_exists($eventOrContext, 'getValue')) {
            $value = $eventOrContext->getValue();
        }

        $ids = is_array($pks) ? array_values($pks) : [];
        if ($ids === [] || $value === null) {
            return;
        }

        if (!$this->supportsContext($context)) {
            return;
        }

        if ((int) $value > 0) {
            return;
        }

        foreach ($ids as $id) {
            $articleId = (int) $id;
            if ($articleId <= 0) {
                continue;
            }

            $article = (object) [
                'id' => $articleId,
                'state' => (int) $value,
            ];

            $this->publishService->onContentDeleted($context, $article);
        }
    }

    /**
     * Check whether a context string is supported by providers.
     *
     * @params string $context Event context.
     *
     * @return  bool  True when the context is for com_content articles.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function supportsContext(string $context): bool
    {
        try {
            /** @var ContentProviderRegistry $registry */
            $registry = Factory::getContainer()->get(ContentProviderRegistry::class);
        } catch (\Throwable) {
            return false;
        }

        return $registry->getProviderForContext($context) !== null;
    }
}
