<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Federation;

use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Adapter\ActivityPubAdapterInterface;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\FollowersModelInterface;
use NX\Component\Fediverse\Administrator\Model\InboundActivitiesModelInterface;
use NX\Component\Fediverse\Administrator\Model\InboxModelInterface;
use NX\Component\Fediverse\Administrator\Model\OutboxModelInterface;
use NX\Component\Fediverse\Administrator\Model\PoliciesModel;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;
use NX\Component\Fediverse\Administrator\Service\Site\BaseUrlProviderInterface;
use Throwable;

/**
 * InboxProcessService Class
 *
 * Provide Inbox Process services.
 *
 * @since  __DEPLOY_VERSION__
 */
final class InboxProcessService
{
    /**
     * Initialize the inbox processing service.
     *
     * Store dependencies required to process inbox items.
     *
     * @params InboxModel                  $inboxModel              Inbox model.
     * @params ActorsModel                 $actorsModel             Actors model.
     * @params FollowersModel              $followersModel          Followers model.
     * @params OutboxModel                 $outboxModel             Outbox model.
     * @params InboundActivitiesModel      $inboundActivitiesModel  Inbound activities model.
     * @params DeliveryServiceInterface    $delivery                Delivery service.
     * @params ActivityPubAdapterInterface $adapter                 ActivityPub adapter.
     * @params JoomlaUserActorMapper       $actorMapper             Actor mapper.
     * @params BaseUrlProviderInterface    $baseUrl                 Base URL provider.
     * @params PoliciesModel               $policiesModel           Domain policies model.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private InboxModelInterface $inboxModel,
        private ActorsModel $actorsModel,
        private FollowersModelInterface $followersModel,
        private OutboxModelInterface $outboxModel,
        private InboundActivitiesModelInterface $inboundActivitiesModel,
        private DeliveryServiceInterface $delivery,
        private ActivityPubAdapterInterface $adapter,
        private JoomlaUserActorMapper $actorMapper,
        private BaseUrlProviderInterface $baseUrl,
        private ?PoliciesModel $policiesModel = null,
    ) {
    }

    /**
     * Process the next inbox batch.
     *
     * Fetch pending inbox items and process each one.
     *
     * @params int $limit Maximum number of items to process.
     *
     * @return  int  Number of processed items.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function processNextBatch(int $limit = 50): int
    {
        $items = $this->inboxModel->fetchUnprocessed($limit);
        $count = 0;

        foreach ($items as $item) {
            $this->processItem((int) $item->id);
            $count++;
        }

        return $count;
    }

    /**
     * Process a single inbox item.
     *
     * Dispatch processing based on the activity type.
     *
     * @params int $inboxId Inbox item identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function processItem(int $inboxId): void
    {
        $item = $this->inboxModel->getById($inboxId);
        if ($item === null) {
            return;
        }

        $type = strtolower((string) ($item->type ?? ''));
        if ($type === 'follow') {
            $this->processFollow($inboxId, $item);

            return;
        }

        if ($type === 'undo') {
            $this->processUndo($inboxId, $item);

            return;
        }

        if (in_array($type, ['create', 'update', 'delete', 'like', 'announce', 'add'], true)) {
            $this->processInboundActivity($inboxId, $item);

            return;
        }

        $this->inboxModel->markIgnored($inboxId, 'Unsupported activity type');
    }

    /**
     * Process inbound activities beyond follow/undo.
     *
     * Persist inbound activities that target local objects.
     *
     * @params int $inboxId Inbox item identifier.
     * @params object $item Inbox item record.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function processInboundActivity(int $inboxId, object $item): void
    {
        $activity = $this->decodeActivity($inboxId, $item);
        if ($activity === null) {
            return;
        }

        // Reject activities from blocked domains.
        $actorUri = isset($activity['actor']) && is_string($activity['actor']) ? $activity['actor'] : '';
        if ($actorUri !== '') {
            $host = parse_url($actorUri, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                try {
                    if ($this->policiesModel !== null && $this->policiesModel->isBlocked($host)) {
                        $this->inboxModel->markIgnored($inboxId, 'domain_blocked');

                        return;
                    }
                } catch (Throwable) {
                    // Policy check failure is non-fatal; allow the activity.
                }
            }
        }

        $remoteActor = $this->resolveRemoteActor($inboxId, $activity);
        if ($remoteActor === null) {
            return;
        }

        $targetUri = $this->extractTargetObjectUri($activity);
        if ($targetUri === '') {
            $this->inboxModel->markIgnored($inboxId, 'Target object missing');

            return;
        }

        if (!$this->isLocalObjectUri($targetUri)) {
            $this->inboxModel->markIgnored($inboxId, 'Target object not local');

            return;
        }

        $activityId = isset($activity['id']) && is_string($activity['id']) && $activity['id'] !== ''
            ? $activity['id']
            : null;
        $activityType = isset($activity['type']) && is_string($activity['type']) && $activity['type'] !== ''
            ? $activity['type']
            : (string) ($item->type ?? 'Unknown');

        $localActorId = isset($item->local_actor_id) ? (int) $item->local_actor_id : null;
        if ($localActorId !== null && $localActorId <= 0) {
            $localActorId = null;
        }

        $objectId = $this->extractObjectIdSegment($targetUri);

        $this->inboundActivitiesModel->insertActivity(
            $inboxId,
            $localActorId,
            $remoteActor->id !== null ? (int) $remoteActor->id : null,
            $activityId,
            $activityType,
            $targetUri,
            $objectId,
            (string) ($item->raw_json ?? '')
        );

        $this->inboxModel->markProcessed($inboxId);
    }

    /**
     * Process a Follow activity.
     *
     * Resolve actors, update follower state, and enqueue acceptance.
     *
     * @params int $inboxId Inbox item identifier.
     * @params object $item Inbox item record.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function processFollow(int $inboxId, object $item): void
    {
        $activity = $this->decodeActivity($inboxId, $item);
        if ($activity === null) {
            return;
        }

        // Reject Follow from blocked domains.
        $actorUri = isset($activity['actor']) && is_string($activity['actor']) ? $activity['actor'] : '';
        if ($actorUri !== '') {
            $host = parse_url($actorUri, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                try {
                    if ($this->policiesModel !== null && $this->policiesModel->isBlocked($host)) {
                        $this->inboxModel->markIgnored($inboxId, 'domain_blocked');

                        return;
                    }
                } catch (Throwable) {
                    // Policy check failure is non-fatal; allow the activity.
                }
            }
        }

        $localActor = $this->resolveLocalActor($item, $activity);
        if ($localActor === null || $localActor->id === null) {
            $this->inboxModel->markFailed($inboxId, 'Local actor not found');

            return;
        }

        $remoteActor = $this->resolveRemoteActor($inboxId, $activity);
        if ($remoteActor === null) {
            return;
        }

        $state = $this->followersModel->getFollowerState((int) $localActor->id, (int) $remoteActor->id);
        if ($state === 'blocked') {
            $reject   = $this->buildRejectActivity($inboxId, $localActor, $activity);
            $outboxId = $this->outboxModel->insertEnvelope((int) $localActor->id, $reject);

            $target = $remoteActor->sharedInboxUrl ?: $remoteActor->inboxUrl;
            $target = is_string($target) ? trim($target) : '';

            if ($target === '') {
                $this->inboxModel->markFailed($inboxId, 'Remote inbox missing');

                return;
            }

            $this->delivery->enqueue($outboxId, [$target]);
            $this->inboxModel->markProcessed($inboxId);

            return;
        }

        // Apply the site-wide follow policy.
        try {
            $followPolicy = (new FediverseConfig())->getFollowPolicy();
        } catch (Throwable) {
            $followPolicy = 'open';
        }

        if ($followPolicy === 'closed') {
            $this->inboxModel->markIgnored($inboxId, 'follow_policy_closed');

            return;
        }

        if ($followPolicy === 'approval') {
            $this->followersModel->upsertFollowerEdge((int) $localActor->id, (int) $remoteActor->id, 'pending');
            $this->inboxModel->markProcessed($inboxId);

            return;
        }

        $this->followersModel->upsertFollowerEdge((int) $localActor->id, (int) $remoteActor->id, 'accepted');

        $accept   = $this->buildAcceptActivity($inboxId, $localActor, $activity);
        $outboxId = $this->outboxModel->insertEnvelope((int) $localActor->id, $accept);

        $target = $remoteActor->sharedInboxUrl ?: $remoteActor->inboxUrl;
        $target = is_string($target) ? trim($target) : '';

        if ($target === '') {
            $this->inboxModel->markFailed($inboxId, 'Remote inbox missing');

            return;
        }

        $this->delivery->enqueue($outboxId, [$target]);
        $this->inboxModel->markProcessed($inboxId);
    }

    /**
     * Process an Undo activity.
     *
     * Resolve actors and remove follower relationships as needed.
     *
     * @params int $inboxId Inbox item identifier.
     * @params object $item Inbox item record.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function processUndo(int $inboxId, object $item): void
    {
        $activity = $this->decodeActivity($inboxId, $item);
        if ($activity === null) {
            return;
        }

        $object = $activity['object'] ?? null;
        if (!is_array($object)) {
            $this->inboxModel->markFailed($inboxId, 'Undo object missing');

            return;
        }

        $objectType = strtolower((string) ($object['type'] ?? ''));
        if ($objectType !== 'follow') {
            $this->inboxModel->markIgnored($inboxId, 'Unsupported undo object');

            return;
        }

        $localActor = $this->resolveLocalActor($item, $object);
        if ($localActor === null || $localActor->id === null) {
            $this->inboxModel->markFailed($inboxId, 'Local actor not found');

            return;
        }

        $remoteActor = $this->resolveRemoteActor($inboxId, $activity);
        if ($remoteActor === null) {
            return;
        }

        $state = $this->followersModel->getFollowerState((int) $localActor->id, (int) $remoteActor->id);
        if ($state === 'blocked') {
            $this->inboxModel->markProcessed($inboxId);

            return;
        }

        $this->followersModel->removeFollowerEdge((int) $localActor->id, (int) $remoteActor->id);
        $this->inboxModel->markProcessed($inboxId);
    }

    /**
     * Decode a raw activity payload.
     *
     * Parse the raw JSON into an associative array.
     *
     * @params int $inboxId Inbox item identifier.
     * @params object $item Inbox item record.
     *
     * @return  ?array  Decoded activity or null on failure.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function decodeActivity(int $inboxId, object $item): ?array
    {
        $raw      = (string) ($item->raw_json ?? '');
        $activity = json_decode($raw, true);
        if (is_array($activity)) {
            return $activity;
        }

        try {
            $envelope = $this->adapter->parseActivity($raw);

            return $envelope->json;
        } catch (Throwable) {
            $this->inboxModel->markFailed($inboxId, 'Invalid activity JSON');

            return null;
        }
    }

    /**
     * Resolve a remote actor for an activity.
     *
     * Fetch the remote actor record and persist it if missing.
     *
     * @params int $inboxId Inbox item identifier.
     * @params array $activity Activity payload.
     *
     * @return  ?Actor  Remote actor instance or null on failure.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveRemoteActor(int $inboxId, array $activity): ?Actor
    {
        $remoteActorUri = self::extractActorUri($activity);
        if ($remoteActorUri === '') {
            $this->inboxModel->markFailed($inboxId, 'Remote actor missing');

            return null;
        }

        $remoteActor = $this->actorsModel->getRemoteByUri($remoteActorUri);
        if ($remoteActor === null) {
            try {
                $remoteActor = $this->adapter->fetchActor($remoteActorUri);
            } catch (Throwable) {
                $remoteActor = null;
            }
        } else {
            $normalizedHandle = $this->normalizeRemoteHandle($remoteActor->handle, $remoteActorUri);
            if ($normalizedHandle !== '' && $normalizedHandle !== $remoteActor->handle) {
                $remoteActor = Actor::newRemote(
                    handle: $normalizedHandle,
                    preferredUsername: $remoteActor->preferredUsername,
                    uri: $remoteActor->uri,
                    inboxUrl: $remoteActor->inboxUrl,
                    outboxUrl: $remoteActor->outboxUrl,
                    sharedInboxUrl: $remoteActor->sharedInboxUrl,
                    publicKeyPem: $remoteActor->publicKeyPem,
                    profileJson: $remoteActor->profileJson,
                    isEnabled: $remoteActor->isEnabled,
                );
            }
        }

        if ($remoteActor === null) {
            $this->inboxModel->markFailed($inboxId, 'Remote actor fetch failed');

            return null;
        }

        $remoteActorId = $remoteActor->id;
        if ($remoteActorId === null) {
            $remoteActorId = $this->actorsModel->upsertRemote($remoteActor);
            $remoteActor   = $remoteActor->withId($remoteActorId);
        } else {
            $normalizedId = $this->actorsModel->upsertRemote($remoteActor);
            $remoteActor  = $remoteActor->withId($normalizedId);
        }

        return $remoteActor;
    }

    /**
     * Normalize a remote handle to include the domain.
     *
     * Append the actor URI host when the handle lacks a domain suffix.
     *
     * @params string $handle Base handle.
     * @params string $actorUri Actor URI for domain extraction.
     *
     * @return  string  Normalized handle.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeRemoteHandle(string $handle, string $actorUri): string
    {
        $handle = trim($handle);
        if ($handle === '') {
            return '';
        }

        $host = parse_url($actorUri, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && !str_contains($handle, '@')) {
            return $handle . '@' . $host;
        }

        return $handle;
    }

    /**
     * Resolve the local actor for an activity.
     *
     * Determine the local actor from the inbox item or activity object.
     *
     * @params object $item Inbox item record.
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  ?Actor  Local actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveLocalActor(object $item, array $activity): ?Actor
    {
        $localActorId = $item->local_actor_id ?? null;
        if ($localActorId !== null) {
            return $this->actorsModel->getById((int) $localActorId);
        }

        $objectUri = self::extractObjectUri($activity);
        if ($objectUri === '') {
            return null;
        }

        $actor = $this->actorsModel->getLocalByUri($objectUri);
        if ($actor !== null) {
            return $this->refreshLocalActorForBaseUrl($actor);
        }

        return $this->resolveLocalActorByHandleFromUri($objectUri);
    }

    /**
     * Resolve a local actor by handle derived from the object URI.
     *
     * Match local actor handles when the URI host matches this instance.
     *
     * @params string $objectUri Activity object URI.
     *
     * @return  ?Actor  Local actor instance or null when not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveLocalActorByHandleFromUri(string $objectUri): ?Actor
    {
        $baseUrl = $this->baseUrl->getBaseUrl();
        $baseHost = Uri::getInstance($baseUrl)->getHost();
        $objectHost = parse_url($objectUri, PHP_URL_HOST) ?? '';

        if ($baseHost !== '' && $objectHost !== '' && !hash_equals($baseHost, $objectHost)) {
            return null;
        }

        $path = parse_url($objectUri, PHP_URL_PATH) ?? '';
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (!preg_match('~^/ap/actors/([^/]+)~', $path, $matches)) {
            return null;
        }

        $handle = rawurldecode($matches[1]);
        if ($handle === '') {
            return null;
        }

        $actor = $this->actorsModel->getLocalByHandle($handle);
        if ($actor === null) {
            return null;
        }

        return $this->refreshLocalActorForBaseUrl($actor);
    }

    /**
     * Refresh local actor URL fields for the current base URL.
     *
     * Update core URLs when the stored actor URI does not match the base host/scheme.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  Actor  Updated or original actor.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function refreshLocalActorForBaseUrl(Actor $actor): Actor
    {
        if ($actor->id === null || $actor->userId === null) {
            return $actor;
        }

        if (!$this->shouldRefreshLocalActor($actor)) {
            return $actor;
        }

        $baseUrl = $this->baseUrl->getBaseUrl();
        $mapped = $this->actorMapper->mapLocalActor((int) $actor->userId, $actor->preferredUsername, $baseUrl);
        $this->actorsModel->updateLocalCoreFields((int) $actor->id, $mapped);

        return $this->actorsModel->getById((int) $actor->id) ?? $actor;
    }

    /**
     * Check if a local actor should be refreshed for the current base URL.
     *
     * Compare host and scheme of the actor URI against the base URL.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  bool  True when the actor should be refreshed.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function shouldRefreshLocalActor(Actor $actor): bool
    {
        if ($actor->userId === null) {
            return false;
        }

        $baseUrl = $this->baseUrl->getBaseUrl();
        $baseUri = Uri::getInstance($baseUrl);
        $baseHost = $baseUri->getHost();
        $baseScheme = strtolower((string) $baseUri->getScheme());

        $actorUri = Uri::getInstance($actor->uri);
        $actorHost = $actorUri->getHost();
        $actorScheme = strtolower((string) $actorUri->getScheme());

        if ($baseHost !== '' && $actorHost !== '' && !hash_equals($baseHost, $actorHost)) {
            return true;
        }

        if ($baseScheme !== '' && $actorScheme !== '' && !hash_equals($baseScheme, $actorScheme)) {
            return true;
        }

        return false;
    }

    /**
     * Extract the actor URI from an activity.
     *
     * Read the actor id from the activity payload.
     *
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Actor URI or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function extractActorUri(array $activity): string
    {
        $actor = $activity['actor'] ?? null;
        if (is_string($actor)) {
            return $actor;
        }

        if (is_array($actor) && isset($actor['id']) && is_string($actor['id'])) {
            return $actor['id'];
        }

        return '';
    }

    /**
     * Extract the object URI from an activity.
     *
     * Read the object id from the activity payload.
     *
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Object URI or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function extractObjectUri(array $activity): string
    {
        $object = $activity['object'] ?? null;
        if (is_string($object)) {
            return $object;
        }

        if (is_array($object) && isset($object['id']) && is_string($object['id'])) {
            return $object['id'];
        }

        return '';
    }

    /**
     * Extract the target object URI from an activity.
     *
     * Prefer inReplyTo for Create activities, otherwise use the object id.
     *
     * @params array<string,mixed> $activity Activity payload.
     *
     * @return  string  Target object URI or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractTargetObjectUri(array $activity): string
    {
        $type = strtolower((string) ($activity['type'] ?? ''));
        if ($type === 'create') {
            $object = $activity['object'] ?? null;
            if (is_array($object)) {
                $inReplyTo = $object['inReplyTo'] ?? null;
                if (is_string($inReplyTo) && $inReplyTo !== '') {
                    return $inReplyTo;
                }
                if (is_array($inReplyTo) && isset($inReplyTo['id']) && is_string($inReplyTo['id'])) {
                    return $inReplyTo['id'];
                }
                if (isset($object['id']) && is_string($object['id']) && $object['id'] !== '') {
                    return $object['id'];
                }
            }
        }

        return self::extractObjectUri($activity);
    }

    /**
     * Check if a target object URI is local.
     *
     * Ensure the object URI points to this site's object endpoint.
     *
     * @params string $uri Target object URI.
     *
     * @return  bool  True when the object is local.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isLocalObjectUri(string $uri): bool
    {
        $baseUrl  = $this->baseUrl->getBaseUrl();
        $baseHost = Uri::getInstance($baseUrl)->getHost();
        $host     = parse_url($uri, PHP_URL_HOST) ?? '';

        if ($baseHost !== '' && $host !== '' && !hash_equals($baseHost, $host)) {
            return false;
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '';

        return is_string($path) && str_starts_with($path, '/ap/objects/');
    }

    /**
     * Extract the object id segment from a target URI.
     *
     * @params string $uri Target object URI.
     *
     * @return  ?string  Object id segment or null when missing.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractObjectIdSegment(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '';
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (!preg_match('~^/ap/objects/([^/]+)~', $path, $matches)) {
            return null;
        }

        $segment = rawurldecode($matches[1]);

        return $segment !== '' ? $segment : null;
    }

    /**
     * Build an Accept activity envelope.
     *
     * Create an Accept response for a Follow activity.
     *
     * @params int $inboxId Inbox item identifier.
     * @params Actor $localActor Local actor instance.
     * @params array<string,mixed> $activity Follow activity payload.
     *
     * @return  ActivityEnvelope  Accept activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildAcceptActivity(int $inboxId, Actor $localActor, array $activity): ActivityEnvelope
    {
        $token      = 'accept-' . $inboxId;
        $activityId = $this->actorMapper->activityUri(
            self::resolveBaseUrlForActor($localActor, $this->baseUrl->getBaseUrl()),
            $token
        );

        $remoteActorUri = self::extractActorUri($activity);
        $object = $activity;

        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => 'Accept',
            'actor'    => $localActor->uri,
            'object'   => $object,
        ];

        if ($remoteActorUri !== '') {
            $accept['to'] = [$remoteActorUri];
        }

        return ActivityEnvelope::fromJson($accept);
    }

    /**
     * Build a Reject activity envelope.
     *
     * Create a Reject response for a Follow activity.
     *
     * @params int $inboxId Inbox item identifier.
     * @params Actor $localActor Local actor instance.
     * @params array<string,mixed> $activity Follow activity payload.
     *
     * @return  ActivityEnvelope  Reject activity envelope.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildRejectActivity(int $inboxId, Actor $localActor, array $activity): ActivityEnvelope
    {
        $token      = 'reject-' . $inboxId;
        $activityId = $this->actorMapper->activityUri(
            self::resolveBaseUrlForActor($localActor, $this->baseUrl->getBaseUrl()),
            $token
        );

        $remoteActorUri = self::extractActorUri($activity);
        $object = $activity;

        $reject = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $activityId,
            'type'     => 'Reject',
            'actor'    => $localActor->uri,
            'object'   => $object,
        ];

        if ($remoteActorUri !== '') {
            $reject['to'] = [$remoteActorUri];
        }

        return ActivityEnvelope::fromJson($reject);
    }

    /**
     * Resolve the best base URL for outbound activities.
     *
     * Prefer the actor URI host/scheme to keep activity ids stable.
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

        $actorUri = Uri::getInstance($uri);
        $scheme = $actorUri->getScheme();
        $host = $actorUri->getHost();
        if ($scheme === '' || $host === '') {
            return $fallback;
        }

        $base = $scheme . '://' . $host;
        $port = $actorUri->getPort();
        if ($port !== null) {
            $base .= ':' . $port;
        }

        return $base;
    }
}
