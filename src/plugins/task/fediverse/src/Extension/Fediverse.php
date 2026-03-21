<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      plg_task_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Plugin\Task\Fediverse\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use NX\Component\Fediverse\Administrator\Model\InboxModel;
use NX\Component\Fediverse\Administrator\Model\OutboxModel;
use NX\Component\Fediverse\Administrator\Model\DeliveryQueueModel;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\FollowersModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\MVC\FediverseMVCFactory;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Federation\InboxProcessService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Security\KeyRotationService;
use NX\Component\Fediverse\Administrator\Service\Security\KeyVaultService;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;
use Throwable;

/**
 * Fediverse Class
 *
 * Provide the Fediverse task plugin.
 *
 * @since  __DEPLOY_VERSION__
 */

final class Fediverse extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected $autoloadLanguage = true;
    private ?FediverseConfig $fediverseConfig = null;

    /**
     * Initialize the task plugin.
     *
     * Store services required for scheduled task execution.
     *
     * @params InboxProcessService $inboxProcessService Inbox processor service.
     * @params DeliveryServiceInterface $deliveryService Delivery service.
     * @params array $config Plugin configuration.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private readonly InboxProcessService $inboxProcessService,
        private readonly DeliveryServiceInterface $deliveryService,
        private readonly ActorResolverServiceInterface $actorResolverService,
        array $config = []
    ) {
        parent::__construct($config);
    }

    protected const array TASKS_MAP = [
        'fediverse.inbox_worker' => [
            'langConstPrefix' => 'PLG_TASK_FEDIVERSE_INBOX_WORKER',
            'form'            => 'inbox_options',
            'method'          => 'inbox',
        ],
        'fediverse.delivery_worker' => [
            'langConstPrefix' => 'PLG_TASK_FEDIVERSE_DELIVERY_WORKER',
            'form'            => 'delivery_options',
            'method'          => 'deliver',
        ],
        'fediverse.key_rotation' => [
            'langConstPrefix' => 'PLG_TASK_FEDIVERSE_KEY_ROTATION',
            'form'            => 'key_rotation_options',
            'method'          => 'rotateKeys',
        ],
        'fediverse.cleanup' => [
            'langConstPrefix' => 'PLG_TASK_FEDIVERSE_CLEANUP',
            'form'            => 'cleanup_options',
            'method'          => 'cleanup',
        ],
    ];

    /**
     * Get subscribed Joomla events.
     *
     * Return the event map for task registration and execution.
     *
     * @return  array  Subscribed event map.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
        ];
    }

    /**
     * Execute the inbox worker task.
     *
     * Process a batch of inbound activities for the scheduler.
     *
     * @params ExecuteTaskEvent $event Task execution event.
     *
     * @return  int  Task execution status.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function inbox(ExecuteTaskEvent $event): int
    {
        if (!$this->getFediverseConfig()->isInboxWorkerEnabled()) {
            $this->logTask('Fediverse inbox worker is disabled by component options.', 'info');

            return Status::OK;
        }

        try {
            $count = $this->inboxProcessService->processNextBatch();
            $this->logTask(sprintf('Processed %d items.', $count), 'info');
        } catch (Throwable $e) {
            $this->logTask('Fediverse inbox worker failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        return Status::OK;
    }

    /**
     * Execute the delivery worker task.
     *
     * Deliver a batch of queued outbound activities.
     *
     * @params ExecuteTaskEvent $event Task execution event.
     *
     * @return  int  Task execution status.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliver(ExecuteTaskEvent $event): int
    {
        if (!$this->getFediverseConfig()->isDeliveryWorkerEnabled()) {
            $this->logTask('Fediverse delivery worker is disabled by component options.', 'info');

            return Status::OK;
        }

        try {
            $count = $this->deliveryService->deliverNextBatch();
            $this->logTask(sprintf('Delivered %d items.', $count), 'info');
        } catch (Throwable $e) {
            $this->logTask('Fediverse delivery worker failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        return Status::OK;
    }

    /**
     * Execute the key rotation task.
     *
     * Rotate signing keys for local actors (all or specific).
     *
     * @params ExecuteTaskEvent $event Task execution event.
     *
     * @return  int  Task execution status.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateKeys(ExecuteTaskEvent $event): int
    {
        try {
            $params = $event->getArgument('params', []);
            $handle = is_array($params) ? ($params['handle'] ?? '') : '';
            $userId = is_array($params) ? (int) ($params['user_id'] ?? 0) : 0;
            $rotationService = Factory::getContainer()->get(KeyRotationService::class);

            if (is_string($handle) && trim($handle) !== '') {
                $handle = trim($handle);
                $rotated = $rotationService->rotateForHandle($handle, null, true);
                $this->logTask(
                    $rotated
                        ? sprintf('Rotated signing key for handle "%s".', $handle)
                        : sprintf('Signing key rotation skipped for handle "%s".', $handle),
                    $rotated ? 'info' : 'warning'
                );

                return Status::OK;
            }

            if ($userId > 0) {
                $rotated = $rotationService->rotateForUserId($userId, null, true);
                $this->logTask(
                    $rotated
                        ? sprintf('Rotated signing key for user id %d.', $userId)
                        : sprintf('Signing key rotation skipped for user id %d.', $userId),
                    $rotated ? 'info' : 'warning'
                );

                return Status::OK;
            }

            $rotated = $rotationService->rotateDueForAllLocalActors(
                $this->getFediverseConfig()->getKeyRotationDays(),
                true
            );
            $this->logTask(sprintf('Rotated signing keys for %d local actors.', $rotated), 'info');
        } catch (Throwable $e) {
            $this->logTask('Fediverse key rotation failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        return Status::OK;
    }

    /**
     * Execute the cleanup task.
     *
     * Prune old inbox, outbox, and delivery queue rows based on retention settings.
     *
     * @params ExecuteTaskEvent $event Task execution event.
     *
     * @return  int  Task execution status.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function cleanup(ExecuteTaskEvent $event): int
    {
        try {
            $config = $this->getFediverseConfig();
            $db     = Factory::getContainer()->get('db');

            $inboxDays    = $config->getInboxRetentionDays();
            $outboxDays   = $config->getOutboxRetentionDays();
            $deliveryDays = $config->getDeliveryQueueRetentionDays();

            $inboxDeleted    = 0;
            $outboxDeleted   = 0;
            $deliveryDeleted = 0;

            if ($inboxDays > 0) {
                $inboxModel   = new InboxModel($db);
                $inboxDeleted = $inboxModel->deleteOlderThan($inboxDays);
            }

            if ($outboxDays > 0) {
                $outboxModel   = new OutboxModel($db);
                $outboxDeleted = $outboxModel->deleteOlderThan($outboxDays);
            }

            if ($deliveryDays > 0) {
                $deliveryModel   = new DeliveryQueueModel($db);
                $deliveryDeleted = $deliveryModel->deleteOlderThan($deliveryDays);
            }

            $this->logTask(
                sprintf(
                    'Cleanup: removed %d inbox, %d outbox, %d delivery queue rows.',
                    $inboxDeleted,
                    $outboxDeleted,
                    $deliveryDeleted
                ),
                'info'
            );
        } catch (Throwable $e) {
            $this->logTask('Fediverse cleanup task failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        }

        return Status::OK;
    }

    /**
     * Rotate signing keys for all local actors.
     *
     * @return  int  Number of actors rotated.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function rotateKeysForAllLocalActors(): int
    {
        $db = Factory::getContainer()->get('db');
        $thresholdDays = $this->getFediverseConfig()->getKeyRotationDays();
        $query = $db->getQuery(true)
            ->select($db->quoteName('handle'))
            ->from($db->quoteName('#__fediverse_actors'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('local'))
            ->where($db->quoteName('is_enabled') . ' = 1');

        $db->setQuery($query);
        $handles = $db->loadColumn() ?: [];

        $count = 0;
        foreach ($handles as $handle) {
            $handle = is_string($handle) ? trim($handle) : '';
            if ($handle === '') {
                continue;
            }

            if ($this->rotateSigningKeyWithAnnouncement($handle, $thresholdDays)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Rotate the signing key and announce the update.
     *
     * Deliver an Update activity signed with the current key before rotating.
     *
     * @params string $handle Actor handle.
     * @params ?int $thresholdDays Optional rotation age threshold.
     *
     * @return  bool  True when rotation occurred.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function rotateSigningKeyWithAnnouncement(string $handle, ?int $thresholdDays = null): bool
    {
        $handle = trim($handle);
        if ($handle === '') {
            return false;
        }

        $actor = $this->fetchLocalActorByHandle($handle);
        if ($actor === null || $actor->id === null) {
            return false;
        }

        $keysModel = $this->requireKeysModel();
        $actorsModel = $this->requireActorsModel();
        if ($keysModel === null || $actorsModel === null) {
            return false;
        }

        if ($thresholdDays !== null && $thresholdDays > 0) {
            if (!$this->isKeyRotationDue((int) $actor->id, $thresholdDays, $keysModel)) {
                return false;
            }
        }

        $currentKey = $keysModel->getActiveKeyMeta((int) $actor->id);
        $currentKeyId = is_object($currentKey) && is_string($currentKey->key_id_uri ?? null)
            ? (string) $currentKey->key_id_uri
            : '';
        if ($currentKeyId === '') {
            return false;
        }

        $keyVault = new KeyVaultService();
        $pair = $keyVault->generateRsaKeyPair(2048);
        $newKeyId = $currentKeyId;

        $activity = $this->buildActorUpdateActivity($actor, $handle, $newKeyId, $pair['public_key_pem']);
        if ($activity !== null) {
            $this->deliverActorUpdateToFollowers($activity, (int) $actor->id, $currentKeyId);
        }

        $privateEnc = $keyVault->encryptPrivateKey($pair['private_key_pem']);
        $keysModel->releaseKeyIdUriForActor((int) $actor->id, $newKeyId);
        $keysModel->rotateActiveKeysForActor((int) $actor->id);
        $keysModel->insertActiveKey((int) $actor->id, $newKeyId, $privateEnc, $pair['public_key_pem']);
        $actorsModel->setPublicKeyPem((int) $actor->id, $pair['public_key_pem']);

        return true;
    }

    /**
     * Rotate a signing key for a user id and announce the update.
     *
     * Resolve the local actor handle and perform a key rotation.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function rotateSigningKeyForUserIdWithAnnouncement(int $userId): void
    {
        $actor = $this->fetchLocalActorByUserId($userId);
        if ($actor === null) {
            return;
        }

        $this->rotateSigningKeyWithAnnouncement($actor->handle);
    }

    /**
     * Build an Update activity for an actor document.
     *
     * Assemble the activity envelope for key rotation updates.
     *
     * @params Actor $actor Actor instance.
     * @params string $handle Actor handle.
     * @params ?string $keyIdUri Key id URI.
     * @params string $publicKeyPem Public key PEM.
     *
     * @return  ?ActivityEnvelope  Activity envelope or null when invalid.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildActorUpdateActivity(
        Actor $actor,
        string $handle,
        ?string $keyIdUri,
        string $publicKeyPem
    ): ?ActivityEnvelope {
        $actorUri = trim($actor->uri);
        if ($actorUri === '') {
            return null;
        }

        $actorDoc = $this->buildActorDocumentPayload($actor, $keyIdUri, $publicKeyPem);
        $activityId = $this->buildActorUpdateActivityId($handle);

        $update = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id'       => $activityId,
            'type'     => 'Update',
            'actor'    => $actorUri,
            'object'   => $actorDoc,
            'to'       => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc'       => [rtrim($actorUri, '/') . '/followers'],
        ];

        return ActivityEnvelope::fromJson($update);
    }

    /**
     * Deliver an actor Update activity to followers.
     *
     * Sign the payload with the provided key id and post to follower inboxes.
     *
     * @params ActivityEnvelope $activity Activity envelope.
     * @params int $actorId Local actor identifier.
     * @params string $signingKeyId Key id URI to sign with.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function deliverActorUpdateToFollowers(
        ActivityEnvelope $activity,
        int $actorId,
        string $signingKeyId
    ): void {
        $followersModel = $this->requireFollowersModel();
        $signatureService = $this->requireSignatureService();
        if ($followersModel === null || $signatureService === null) {
            return;
        }

        $targets = $followersModel->getAcceptedFollowerInboxUrls($actorId);
        if ($targets === []) {
            return;
        }

        $payload = $activity->toJsonString();
        foreach ($targets as $target) {
            try {
                $res = $signatureService->signedPostRequest($target, $payload, $signingKeyId);
            } catch (Throwable $e) {
                $this->logTask(
                    sprintf('Key rotation update delivery failed for %s: %s', $target, $e->getMessage()),
                    'warning'
                );
                continue;
            }

            $status = is_array($res) ? (int) ($res['status'] ?? 0) : 0;
            if ($status < 200 || $status >= 300) {
                $this->logTask(
                    sprintf('Key rotation update delivery failed for %s with status %d.', $target, $status),
                    'warning'
                );
            }
        }
    }

    /**
     * Build an ActivityPub actor document payload.
     *
     * Serialize the actor details and key material for delivery.
     *
     * @params Actor $actor Actor instance.
     * @params ?string $keyIdUri Key id URI.
     * @params string $publicKeyPem Public key PEM.
     *
     * @return  array<string,mixed> Actor document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildActorDocumentPayload(Actor $actor, ?string $keyIdUri, string $publicKeyPem): array
    {
        $actorUri = $actor->uri;
        $keyIdUri = is_string($keyIdUri) && trim($keyIdUri) !== '' ? trim($keyIdUri) : $actorUri . '#main-key';
        $keyIdUri = self::normalizeKeyIdUri($keyIdUri);

        $doc = [
            '@context'          => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id'                => $actorUri,
            'type'              => 'Person',
            'preferredUsername' => $actor->preferredUsername,
            'inbox'             => $actor->inboxUrl,
            'outbox'            => $actor->outboxUrl,
            'publicKey'         => [
                'id'           => $keyIdUri,
                'owner'        => $actorUri,
                'type'         => 'Key',
                'publicKeyPem' => $publicKeyPem,
            ],
        ];

        if ($actor->sharedInboxUrl !== null && trim($actor->sharedInboxUrl) !== '') {
            $doc['endpoints'] = ['sharedInbox' => $actor->sharedInboxUrl];
        }

        return $doc;
    }

    /**
     * Normalize a key id URI for outbound use.
     *
     * Convert legacy fragment key ids to path-based key ids.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Normalized key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function normalizeKeyIdUri(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)#(main-key.*)$~', $keyId, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return $keyId;
    }

    /**
     * Build an activity id for actor updates.
     *
     * Generate a unique activity URI using the site base URL.
     *
     * @params string $handle Actor handle.
     *
     * @return  string Activity id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildActorUpdateActivityId(string $handle): string
    {
        $baseUrl = (new JoomlaBaseUrlProvider($this->getFediverseConfig()))->getBaseUrl();
        $token = 'actor-update-' . ($handle !== '' ? $handle : 'actor') . '-' . gmdate('YmdHis');

        try {
            $token .= '-' . bin2hex(random_bytes(4));
        } catch (Throwable) {
            $token .= '-' . uniqid();
        }

        $mapper = new JoomlaUserActorMapper($this->getFediverseConfig());

        return $mapper->activityUri($baseUrl, $token);
    }

    /**
     * Build a unique key id URI for an actor.
     *
     * Append a unique suffix to avoid key id reuse across rotations.
     *
     * @params string $actorUri Actor URI.
     *
     * @return  string Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildKeyIdUri(string $actorUri): string
    {
        $actorUri = trim($actorUri);
        $timestamp = gmdate('YmdHis');

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (Throwable) {
            $suffix = uniqid();
        }

        return $actorUri . '#main-key-' . $timestamp . '-' . $suffix;
    }

    /**
     * Fetch a local actor by handle.
     *
     * Resolve the local actor record for the provided handle.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?Actor Actor instance or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function fetchLocalActorByHandle(string $handle): ?Actor
    {
        $actorsModel = $this->requireActorsModel();
        if ($actorsModel === null) {
            return null;
        }

        return $actorsModel->getLocalByHandle($handle);
    }

    /**
     * Fetch a local actor by user id.
     *
     * Resolve the local actor record for the provided user id.
     *
     * @params int $userId Joomla user identifier.
     *
     * @return  ?Actor Actor instance or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function fetchLocalActorByUserId(int $userId): ?Actor
    {
        $actorsModel = $this->requireActorsModel();
        if ($actorsModel === null) {
            return null;
        }

        return $actorsModel->getLocalByUserId($userId);
    }

    /**
     * Resolve the signature service.
     *
     * @return  ?HttpSignaturServiceInterface Signature service or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireSignatureService(): ?HttpSignaturServiceInterface
    {
        $service = Factory::getContainer()->get(HttpSignaturServiceInterface::class);

        return $service instanceof HttpSignaturServiceInterface ? $service : null;
    }

    /**
     * Resolve the Actors model.
     *
     * @return  ?ActorsModel Actors model or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireActorsModel(): ?ActorsModel
    {
        $mvcFactory = Factory::getContainer()->get(FediverseMVCFactory::class);
        $model = $mvcFactory->createModel('Actors', 'Administrator', ['ignore_request' => true]);

        return $model instanceof ActorsModel ? $model : null;
    }

    /**
     * Resolve the Keys model.
     *
     * @return  ?KeysModel Keys model or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireKeysModel(): ?KeysModel
    {
        $mvcFactory = Factory::getContainer()->get(FediverseMVCFactory::class);
        $model = $mvcFactory->createModel('Keys', 'Administrator', ['ignore_request' => true]);

        return $model instanceof KeysModel ? $model : null;
    }

    /**
     * Resolve the Followers model.
     *
     * @return  ?FollowersModel Followers model or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function requireFollowersModel(): ?FollowersModel
    {
        $mvcFactory = Factory::getContainer()->get(FediverseMVCFactory::class);
        $model = $mvcFactory->createModel('Followers', 'Administrator', ['ignore_request' => true]);

        return $model instanceof FollowersModel ? $model : null;
    }

    /**
     * Check whether a key rotation is due.
     *
     * Compare the active key age to the configured threshold.
     *
     * @params int $actorId Actor identifier.
     * @params int $thresholdDays Rotation threshold in days.
     * @params KeysModel $keysModel Keys model.
     *
     * @return  bool  True when rotation should occur.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isKeyRotationDue(int $actorId, int $thresholdDays, KeysModel $keysModel): bool
    {
        $createdAt = $keysModel->getActiveKeyCreatedAt($actorId);
        if ($createdAt === null) {
            return true;
        }

        try {
            $created = new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return true;
        }

        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d days', max(1, $thresholdDays)));

        return $created <= $cutoff;
    }

    /**
     * Get the Fediverse component configuration.
     *
     * @return  FediverseConfig  Configuration helper.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getFediverseConfig(): FediverseConfig
    {
        if ($this->fediverseConfig === null) {
            $this->fediverseConfig = new FediverseConfig();
        }

        return $this->fediverseConfig;
    }

}
