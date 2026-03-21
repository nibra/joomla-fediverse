<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Exception\UserNotFoundException;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\KeysModel;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverService;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use Throwable;

/**
 * ActorController Class
 *
 * Handle Actor requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class ActorController extends BaseController
{
    /**
     * Handle an actor GET request.
     *
     * Resolve an actor by handle and return the ActivityPub actor document.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function get(string $handle): void
    {
        $handle = trim($handle);
        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400, 'application/json');

            return;
        }

        try {
            $actorsModel = $this->factory->createModel('Actors', 'Administrator');

            if (!$actorsModel instanceof ActorsModel) {
                $this->sendJson(['error' => 'Internal error'], 500, 'application/json');

                return;
            }

            $actor = $actorsModel->getLocalByHandle($handle);
            if ($actor !== null && $actor->isLocal() && $actor->userId !== null) {
                $actor = $this->refreshLocalActor($actor);
            }
            if ($actor === null) {
                $actor = $this->provisionActorOnDemand($handle);
            }

            if ($actor === null || !$actor->isLocal() || !$actor->isEnabled) {
                $this->sendJson(['error' => 'Actor not found'], 404, 'application/json');

                return;
            }

            [$keyId, $publicKeyPem] = $this->resolveActorKeyMaterial($actor);

            if ($publicKeyPem === null || trim($publicKeyPem) === '') {
                $this->sendJson(['error' => 'Actor key not available'], 503, 'application/json');

                return;
            }

            $accept      = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
            $contentType = self::negotiateActorContentType($accept);

            $payload = self::buildActorDocument($actor, $keyId, $publicKeyPem);
            $this->sendJson($payload, 200, $contentType);
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Handle an actor key request.
     *
     * Resolve and return the public key document for the actor.
     *
     * @params string $handle Actor handle.
     * @params string $keyPart Key identifier segment.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function key(string $handle, string $keyPart = 'main-key'): void
    {
        $handle = trim($handle);
        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400, 'application/json');

            return;
        }

        $keyPart = trim($keyPart);
        if ($keyPart === '') {
            $keyPart = 'main-key';
        }

        if (!str_starts_with($keyPart, 'main-key')) {
            $this->sendJson(['error' => 'Invalid key identifier'], 400, 'application/json');

            return;
        }

        try {
            $actorsModel = $this->factory->createModel('Actors', 'Administrator');

            if (!$actorsModel instanceof ActorsModel) {
                $this->sendJson(['error' => 'Internal error'], 500, 'application/json');

                return;
            }

            $actor = $actorsModel->getLocalByHandle($handle);
            if ($actor !== null && $actor->isLocal() && $actor->userId !== null) {
                $actor = $this->refreshLocalActor($actor);
            }
            if ($actor === null) {
                $actor = $this->provisionActorOnDemand($handle);
            }

            if ($actor === null || !$actor->isLocal() || !$actor->isEnabled) {
                $this->sendJson(['error' => 'Actor not found'], 404, 'application/json');

                return;
            }

            $keyIdUri = $actor->uri . '/' . $keyPart;
            $legacyKeyIdUri = self::legacyKeyIdUri($keyIdUri);
            $publicKeyPem = $actor->publicKeyPem;
            $keyMetaFound = false;

            if ($actor->id !== null) {
                $keysModel = $this->factory->createModel('Keys', 'Administrator');
                if ($keysModel instanceof KeysModel) {
                    $meta = $keysModel->getByKeyIdUri($legacyKeyIdUri);
                    if ($meta !== null) {
                        $keyMetaFound = true;
                        $publicKeyPem = is_string($meta->public_key_pem ?? null)
                            ? (string) $meta->public_key_pem
                            : $publicKeyPem;
                    }
                }
            }

            if (!$keyMetaFound && $keyPart !== 'main-key') {
                $this->sendJson(['error' => 'Key not found'], 404, 'application/json');

                return;
            }

            if ($publicKeyPem === null || trim($publicKeyPem) === '') {
                $this->sendJson(['error' => 'Actor key not available'], 503, 'application/json');

                return;
            }

            $payload = [
                '@context' => [
                    'https://w3id.org/security/v1',
                    'https://www.w3.org/ns/activitystreams',
                ],
                'id'                => $actor->uri,
                'preferredUsername' => $actor->preferredUsername,
                'publicKey'         => [
                    'id'           => self::normalizeKeyIdUri($legacyKeyIdUri),
                    'owner'        => $actor->uri,
                    'type'         => 'Key',
                    'publicKeyPem' => $publicKeyPem,
                ],
                'type' => $actor->actorType ?? 'Person',
            ];

            $this->sendJson($payload, 200, 'application/activity+json');
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];

            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500, 'application/json');
        }
    }

    /**
     * Refresh a local actor for the current request host.
     *
     * Rebuild the local actor when the stored URLs do not match the current host.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  Actor  Refreshed actor instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function refreshLocalActor(Actor $actor): Actor
    {
        if (!$this->shouldRefreshLocalActor($actor)) {
            return $actor;
        }

        if ($actor->userId === null) {
            return $actor;
        }

        try {
            /** @var ActorResolverService $resolver */
            $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);

            return $resolver->ensureLocalActorForUserId((int) $actor->userId);
        } catch (Throwable) {
            return $actor;
        }
    }

    /**
     * Check if a local actor needs refresh for the current host.
     *
     * Compare the stored actor URI host against the current request host.
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

        $currentHost = Uri::getInstance()->getHost();
        if ($currentHost === '') {
            return false;
        }

        $currentScheme = strtolower((string) Uri::getInstance()->getScheme());
        $actorHost = parse_url($actor->uri, PHP_URL_HOST) ?? '';
        if ($actorHost === '') {
            return true;
        }

        if (!hash_equals($actorHost, $currentHost)) {
            return true;
        }

        $actorScheme = strtolower((string) (parse_url($actor->uri, PHP_URL_SCHEME) ?? ''));
        if ($actorScheme === '') {
            return true;
        }

        return $currentScheme !== '' && !hash_equals($actorScheme, $currentScheme);
    }

    /**
     * Build the actor document payload.
     *
     * Assemble an ActivityPub actor document from a local actor.
     *
     * @params Actor $actor Actor instance.
     *
     * @return  array<string,mixed>  Actor document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function buildActorDocument(Actor $actor, ?string $keyIdUri = null, ?string $publicKeyPem = null): array
    {
        $keyIdUri = is_string($keyIdUri) && trim($keyIdUri) !== '' ? trim($keyIdUri) : $actor->uri . '#main-key';
        $publicKeyPem = is_string($publicKeyPem) && trim($publicKeyPem) !== '' ? $publicKeyPem : $actor->publicKeyPem;
        $keyIdUri = self::normalizeKeyIdUri($keyIdUri);

        $doc = [
            '@context'          => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id'                => $actor->uri,
            'type'              => $actor->actorType ?? 'Person',
            'preferredUsername' => $actor->preferredUsername,
            'inbox'             => $actor->inboxUrl,
            'outbox'            => $actor->outboxUrl,
            'publicKey'         => [
                'id'           => $keyIdUri,
                'owner'        => $actor->uri,
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
     * Resolve the active key id and public key for an actor.
     *
     * Prefer the active key record, fall back to actor metadata.
     *
     * @params Actor $actor Actor instance.
     *
     * @return  array{0:?string,1:?string}  Key id URI and public key PEM.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveActorKeyMaterial(Actor $actor): array
    {
        $keyId = null;
        $publicKeyPem = $actor->publicKeyPem;

        if ($actor->id !== null) {
            $keysModel = $this->factory->createModel('Keys', 'Administrator');
            if ($keysModel instanceof KeysModel) {
                $meta = $keysModel->getActiveKeyMeta((int) $actor->id);
                if ($meta !== null) {
                    $keyId = is_string($meta->key_id_uri ?? null) ? (string) $meta->key_id_uri : null;
                    $publicKeyPem = is_string($meta->public_key_pem ?? null) ? (string) $meta->public_key_pem : $publicKeyPem;
                }
            }
        }

        if (!is_string($keyId) || trim($keyId) === '') {
            $keyId = $actor->uri . '#main-key';
        }

        return [$keyId, $publicKeyPem];
    }

    /**
     * Normalize a key id URI for outbound use.
     *
     * Convert fragment-based key ids to path-based key ids.
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
     * Convert key id URIs back to legacy fragment format.
     *
     * Map path-based key ids to fragment-based ids for storage lookup.
     *
     * @params string $keyId Key identifier URI.
     *
     * @return  string  Legacy key id URI.
     *
     * @since  __DEPLOY_VERSION__
     */
    private static function legacyKeyIdUri(string $keyId): string
    {
        $keyId = trim($keyId);
        if ($keyId === '') {
            return '';
        }

        if (preg_match('~^(.*)/main-key(.*)$~', $keyId, $matches)) {
            return $matches[1] . '#main-key' . $matches[2];
        }

        return $keyId;
    }

    /**
     * Negotiate the actor response content type.
     *
     * Select the appropriate content type based on the Accept header.
     *
     * @params string $acceptHeader Accept header value.
     *
     * @return  string  Selected content type.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function negotiateActorContentType(string $acceptHeader): string
    {
        $accept = strtolower($acceptHeader);

        if (str_contains($accept, 'application/ld+json')) {
            return 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        }

        return 'application/activity+json';
    }

    /**
     * Provision a local actor on demand.
     *
     * Create a local actor for a matching handle when possible.
     *
     * @params string $handle Actor handle.
     *
     * @return  ?Actor  Local actor instance or null when not available.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function provisionActorOnDemand(string $handle): ?Actor
    {
        if (!preg_match('/^u([1-9][0-9]*)$/', $handle, $m)) {
            return null;
        }

        /** @var ActorResolverService $resolver */
        $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);

        try {
            return $resolver->ensureLocalActorForUserId((int) $m[1]);
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * Send a JSON response.
     *
     * Encode a payload and emit a JSON response with headers.
     *
     * @params array<string,mixed> $payload Response payload.
     * @params int $status HTTP status code.
     * @params string $contentType Response content type.
     *
     * @return  void  None.
     * @throws  \JsonException  if JSON encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(array $payload, int $status, string $contentType): void
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType . '; charset=utf-8');
            header('Vary: Accept');
        }

        echo $json;

        if (method_exists($this->app, 'close') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->app->close();
        }
    }
}
