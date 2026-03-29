<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Security;

use Joomla\Database\DatabaseInterface;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Helper\ActorProfileDocumentDecorator;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\FollowersModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\MVC\FediverseMVCFactory;
use NX\Component\Fediverse\Administrator\Service\Config\FediverseConfig;
use NX\Component\Fediverse\Administrator\Service\Site\JoomlaBaseUrlProvider;
use NX\Component\Fediverse\Administrator\Service\Security\HttpSignaturServiceInterface;
use NX\Component\Fediverse\Administrator\Service\Security\KeyVaultService;
use Throwable;

/**
 * KeyRotationService Class
 *
 * Provide key rotation services with optional announcements.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeyRotationService
{
    private ?FediverseConfig $config = null;
    private ?JoomlaUserActorMapper $actorMapper = null;
    private ?FediverseMVCFactory $mvcFactory = null;
    private ?HttpSignaturServiceInterface $signatureService = null;
    private ?DatabaseInterface $db = null;

    /**
     * Initialize the key rotation service.
     *
     * @params ?FediverseConfig $config Component configuration.
     * @params ?JoomlaUserActorMapper $actorMapper Actor mapper.
     * @params ?FediverseMVCFactory $mvcFactory MVC factory.
     * @params ?HttpSignaturServiceInterface $signatureService Signature service.
     * @params ?DatabaseInterface $db Database connection.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        ?FediverseConfig $config = null,
        ?JoomlaUserActorMapper $actorMapper = null,
        ?FediverseMVCFactory $mvcFactory = null,
        ?HttpSignaturServiceInterface $signatureService = null,
        ?DatabaseInterface $db = null
    ) {
        $this->config = $config;
        $this->actorMapper = $actorMapper;
        $this->mvcFactory = $mvcFactory;
        $this->signatureService = $signatureService;
        $this->db = $db;
    }

    /**
     * Rotate signing keys for all local actors due for rotation.
     *
     * @params int $thresholdDays Rotation threshold in days.
     * @params bool $announce Whether to announce the rotation.
     *
     * @return  int  Number of actors rotated.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateDueForAllLocalActors(int $thresholdDays, bool $announce = true): int
    {
        $actorsModel = $this->requireActorsModel();
        $keysModel = $this->requireKeysModel();
        if ($actorsModel === null || $keysModel === null) {
            return 0;
        }

        $db = $this->db;
        if ($db === null) {
            return 0;
        }
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

            $actor = $actorsModel->getLocalByHandle($handle);
            if ($actor === null || $actor->id === null) {
                continue;
            }

            if (!$this->isKeyRotationDue((int) $actor->id, $thresholdDays, $keysModel)) {
                continue;
            }

            if ($this->rotateForActor($actor, null, $announce)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Rotate signing key for a specific handle.
     *
     * @params string $handle Actor handle.
     * @params ?int $thresholdDays Optional rotation threshold.
     * @params bool $announce Whether to announce the rotation.
     *
     * @return  bool  True when rotation occurred.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateForHandle(string $handle, ?int $thresholdDays = null, bool $announce = true): bool
    {
        $handle = trim($handle);
        if ($handle === '') {
            return false;
        }

        $actor = $this->fetchLocalActorByHandle($handle);
        if ($actor === null) {
            return false;
        }

        return $this->rotateForActor($actor, $thresholdDays, $announce);
    }

    /**
     * Rotate signing key for a specific user.
     *
     * @params int $userId Joomla user identifier.
     * @params ?int $thresholdDays Optional rotation threshold.
     * @params bool $announce Whether to announce the rotation.
     *
     * @return  bool  True when rotation occurred.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function rotateForUserId(int $userId, ?int $thresholdDays = null, bool $announce = true): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $actor = $this->fetchLocalActorByUserId($userId);
        if ($actor === null) {
            return false;
        }

        return $this->rotateForActor($actor, $thresholdDays, $announce);
    }

    /**
     * Rotate signing key for a resolved actor.
     *
     * @params Actor $actor Actor instance.
     * @params ?int $thresholdDays Optional rotation threshold.
     * @params bool $announce Whether to announce the rotation.
     *
     * @return  bool  True when rotation occurred.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function rotateForActor(Actor $actor, ?int $thresholdDays, bool $announce): bool
    {
        if ($actor->id === null) {
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
        $newKeyId = $this->buildUniqueKeyIdUri((string) $actor->uri, $currentKeyId);

        if ($announce) {
            $activity = $this->buildActorUpdateActivity($actor, $actor->handle, $newKeyId, $pair['public_key_pem']);
            if ($activity !== null) {
                $this->deliverActorUpdateToFollowers($activity, (int) $actor->id, $currentKeyId);
            }
        }

        $privateEnc = $keyVault->encryptPrivateKey($pair['private_key_pem']);
        $keysModel->releaseKeyIdUriForActor((int) $actor->id, $newKeyId);
        $keysModel->rotateActiveKeysForActor((int) $actor->id);
        $keysModel->insertActiveKey((int) $actor->id, $newKeyId, $privateEnc, $pair['public_key_pem']);
        $actorsModel->setPublicKeyPem((int) $actor->id, $pair['public_key_pem']);

        return true;
    }

    /**
     * Build an Update activity for an actor document.
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
                $signatureService->signedPostRequest($target, $payload, $signingKeyId);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Build an ActivityPub actor document payload.
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
            'type'              => $actor->actorType ?? 'Person',
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

        $doc = ActorProfileDocumentDecorator::applyProfileData($doc, $actor->getProfileData());

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
     * Build a unique key id URI for a rotation.
     *
     * @params string $actorUri Actor URI.
     * @params string $fallbackKeyId Fallback key id.
     *
     * @return  string  Key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildUniqueKeyIdUri(string $actorUri, string $fallbackKeyId): string
    {
        $actorUri = trim($actorUri);
        if ($actorUri === '') {
            $actorUri = self::actorUriFromKeyId($fallbackKeyId);
        }

        if ($actorUri === '') {
            return $fallbackKeyId;
        }

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (Throwable) {
            $suffix = substr(str_replace('.', '', uniqid('', true)), 0, 12);
        }

        return $actorUri . '#main-key-' . gmdate('YmdHis') . '-' . $suffix;
    }

    /**
     * Extract actor URI from a key id.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Actor URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function actorUriFromKeyId(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)#main-key.*$~', $keyId, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('~^(.*)/main-key.*$~', $keyId, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return $keyId;
    }

    /**
     * Build an activity id for actor updates.
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

        return $this->getActorMapper()->activityUri($baseUrl, $token);
    }

    /**
     * Fetch a local actor by handle.
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
        return $this->signatureService instanceof HttpSignaturServiceInterface
            ? $this->signatureService
            : null;
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
        $mvcFactory = $this->getMvcFactory();
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
        $mvcFactory = $this->getMvcFactory();
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
        $mvcFactory = $this->getMvcFactory();
        $model = $mvcFactory->createModel('Followers', 'Administrator', ['ignore_request' => true]);

        return $model instanceof FollowersModel ? $model : null;
    }

    /**
     * Check whether a key rotation is due.
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
        } catch (Throwable) {
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
        if ($this->config === null) {
            $this->config = new FediverseConfig();
        }

        return $this->config;
    }

    /**
     * Get the actor mapper.
     *
     * @return  JoomlaUserActorMapper  Actor mapper instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getActorMapper(): JoomlaUserActorMapper
    {
        if ($this->actorMapper === null) {
            $this->actorMapper = new JoomlaUserActorMapper($this->getFediverseConfig());
        }

        return $this->actorMapper;
    }

    /**
     * Get the MVC factory.
     *
     * @return  FediverseMVCFactory MVC factory instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getMvcFactory(): FediverseMVCFactory
    {
        if ($this->mvcFactory === null) {
            throw new \LogicException('FediverseMVCFactory not injected into KeyRotationService');
        }

        return $this->mvcFactory;
    }
}
