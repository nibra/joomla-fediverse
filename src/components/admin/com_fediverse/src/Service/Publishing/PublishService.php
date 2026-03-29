<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Publishing;

use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Model\ContentSettingsModel;
use NX\Component\Fediverse\Administrator\Model\UserSettingsModel;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;
use NX\Component\Fediverse\Administrator\Model\FollowersModelInterface;
use NX\Component\Fediverse\Administrator\Model\OutboxModelInterface;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Event\ContentDeletedEvent;
use NX\Component\Fediverse\Administrator\Event\ContentPublishedEvent;
use NX\Component\Fediverse\Administrator\Event\ContentUpdatedEvent;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Events\FediverseDomainEventDispatcherInterface;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * PublishService Class
 *
 * Provide Publish services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PublishService
{
    /**
     * Initialize the publish service.
     *
     * Store dependencies required to map and deliver activities.
     *
     * @params ContentProviderRegistry $providers Content provider registry.
     * @params ActorResolverServiceInterface $actorResolver Actor resolver service.
     * @params DeliveryServiceInterface $delivery Delivery service.
     * @params BaseUrlProviderInterface $baseUrl Base URL provider.
     * @params FollowersModel $followersModel Followers model.
     * @params OutboxModel $outboxModel Outbox model.
     * @params ?FediverseDomainEventDispatcherInterface $eventDispatcher Domain event dispatcher.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private ContentProviderRegistry $providers,
        private ActorResolverServiceInterface $actorResolver,
        private DeliveryServiceInterface $delivery,
        private BaseUrlProviderInterface $baseUrl,
        private FollowersModelInterface $followersModel,
        private OutboxModelInterface $outboxModel,
        private ?DatabaseInterface $db = null,
        private ?FediverseDomainEventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * Publish a content save event.
     *
     * Convert the saved content item into an ActivityPub activity and enqueue delivery.
     *
     * @params string $context Joomla content context.
     * @params object $item Joomla content item.
     * @params bool $isNew Whether the item is new.
     *
     * @return  void  None.
     * @throws  \Throwable  if actor provisioning fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentSaved(string $context, object $item, bool $isNew): void
    {
        $provider = $this->providers->getProviderForContext($context);
        if ($provider === null) {
            return;
        }

        $item = $this->mergeWithPersistedItem($provider, $item);

        $userId = $provider->resolveUserId($item, $context);
        if ($userId <= 0) {
            return;
        }

        // Skip federation if the author has opted out.
        try {
            $db           = $this->db;
            if ($db === null) {
                throw new \RuntimeException('Database not available');
            }
            if (!$this->isFederationEnabledForContent($db, $context, $userId, $item)) {
                return;
            }
        } catch (Throwable) {
            // DB unavailable: fall through and allow federation (opt-in by default).
        }

        $actor = $this->actorResolver->ensureLocalActorForUserId($userId);
        if ($actor->id === null) {
            return;
        }

        try {
            $baseUrl  = self::resolveBaseUrlForActor($actor, $this->baseUrl->getBaseUrl());
            $activity = $provider->toCreateOrUpdate($actor, $item, $isNew, $baseUrl);
        } catch (Throwable) {
            // Fail closed: do not publish partial / malformed activities.
            return;
        }

        if ($activity->type === 'Update') {
            $objectUri = (string) (($activity->json['object']['id'] ?? '') ?: '');
            if ($objectUri !== '') {
                $this->outboxModel->deleteQueuedUpdatesForObject((int) $actor->id, $objectUri);
            }
        }

        $outboxId = $this->outboxModel->insertEnvelope((int) $actor->id, $activity);

        $targets = $this->followersModel->getAcceptedFollowerInboxUrls((int) $actor->id);
        if ($targets !== []) {
            $this->delivery->enqueue($outboxId, $targets);
        }

        $payload = [
            'context' => $context,
            'item_id' => (int) ($item->id ?? 0),
            'actor_id' => (int) $actor->id,
            'user_id' => $userId,
            'outbox_id' => $outboxId,
            'activity_type' => (string) $activity->type,
            'activity_id' => (string) ($activity->json['id'] ?? ''),
            'object_id' => (string) (($activity->json['object']['id'] ?? '') ?: ''),
            'target_count' => count($targets),
        ];

        $this->eventDispatcher?->dispatch(
            $isNew ? new ContentPublishedEvent($payload) : new ContentUpdatedEvent($payload)
        );
    }

    /**
     * Publish a content deletion event.
     *
     * Convert the deleted content item into an ActivityPub activity and enqueue delivery.
     *
     * @params string $context Joomla content context.
     * @params object $item Joomla content item.
     *
     * @return  void  None.
     * @throws  \Throwable  if actor provisioning fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentDeleted(string $context, object $item): void
    {
        $provider = $this->providers->getProviderForContext($context);
        if ($provider === null) {
            return;
        }

        $userId = $provider->resolveUserId($item, $context);
        if ($userId <= 0) {
            return;
        }

        // Skip federation if the author has opted out.
        try {
            $db           = $this->db;
            if ($db === null) {
                throw new \RuntimeException('Database not available');
            }
            if (!$this->isFederationEnabledForContent($db, $context, $userId, $item)) {
                return;
            }
        } catch (Throwable) {
            // DB unavailable: fall through and allow federation (opt-in by default).
        }

        $actor = $this->actorResolver->ensureLocalActorForUserId($userId);
        if ($actor->id === null) {
            return;
        }

        try {
            $baseUrl  = self::resolveBaseUrlForActor($actor, $this->baseUrl->getBaseUrl());
            $activity = $provider->toDelete($actor, $item, $baseUrl);
        } catch (Throwable) {
            // Fail closed: do not publish partial / malformed activities.
            return;
        }

        $outboxId = $this->outboxModel->insertEnvelope((int) $actor->id, $activity);

        $targets = $this->followersModel->getAcceptedFollowerInboxUrls((int) $actor->id);
        if ($targets !== []) {
            $this->delivery->enqueue($outboxId, $targets);
        }

        $this->eventDispatcher?->dispatch(
            new ContentDeletedEvent([
                'context' => $context,
                'item_id' => (int) ($item->id ?? 0),
                'actor_id' => (int) $actor->id,
                'user_id' => $userId,
                'outbox_id' => $outboxId,
                'activity_type' => (string) $activity->type,
                'activity_id' => (string) ($activity->json['id'] ?? ''),
                'object_id' => (string) (($activity->json['object'] ?? '') ?: ''),
                'target_count' => count($targets),
            ])
        );
    }

    /**
     * Merge a saved content item with its freshly persisted database row.
     *
     * This keeps save-event data while filling in fields such as attribs, images, and tags that may be missing
     * from the event payload.
     *
     * @params ContentProviderInterface $provider Content provider.
     * @params object $item Joomla content item.
     *
     * @return  object  Enriched content item.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function mergeWithPersistedItem(ContentProviderInterface $provider, object $item): object
    {
        $itemId = (int) ($item->id ?? 0);
        if ($itemId <= 0) {
            return $item;
        }

        try {
            $persisted = $provider->fetchById($itemId);
        } catch (Throwable) {
            return $item;
        }

        if ($persisted === null) {
            return $item;
        }

        $merged = clone $persisted;

        foreach (get_object_vars($item) as $property => $value) {
            $merged->$property = $value;
        }

        return $merged;
    }

    /**
     * Check whether federation is enabled for a user and content item.
     *
     * Applies user-level, category-level, then item-level settings.
     *
     * @params DatabaseInterface $db Database connection.
     * @params string $context Joomla content context.
     * @params int $userId Joomla user id.
     * @params object $item Joomla content item.
     *
     * @return  bool  True when federation is enabled.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isFederationEnabledForContent(DatabaseInterface $db, string $context, int $userId, object $item): bool
    {
        $userSettings = new UserSettingsModel($db);
        if (!$userSettings->isFederationEnabled($userId)) {
            return false;
        }

        $contentSettings = new ContentSettingsModel($db);

        // Category-level controls currently apply to Joomla core articles.
        if ($context === 'com_content.article') {
            $categoryId = isset($item->catid) ? (int) $item->catid : 0;
            if ($categoryId > 0 && !$contentSettings->isFederationEnabled('com_content.category', $categoryId)) {
                return false;
            }
        }

        $itemId = (int) ($item->id ?? 0);
        if ($itemId > 0 && !$contentSettings->isFederationEnabled($context, $itemId)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the best base URL for outbound activities.
     *
     * Prefer the actor URI host/scheme to keep object ids stable.
     *
     * @params Actor $actor Local actor.
     * @params string $fallback Fallback base URL.
     *
     * @return  string  Base URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function resolveBaseUrlForActor(Actor $actor, string $fallback): string
    {
        $uri = trim((string) $actor->uri);
        if ($uri === '') {
            return $fallback;
        }

        $parts = parse_url($uri);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $fallback;
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        return $base;
    }
}
