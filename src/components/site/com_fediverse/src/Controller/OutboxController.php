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
use Joomla\Database\DatabaseDriver;
use NX\Component\Fediverse\Administrator\Domain\Activity\ActivityEnvelope;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Exception\OAuthException;
use NX\Component\Fediverse\Administrator\Http\RequestContext;
use NX\Component\Fediverse\Administrator\Mapper\JoomlaUserActorMapper;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Model\FollowersModel;
use NX\Component\Fediverse\Administrator\Model\OutboxModel;
use NX\Component\Fediverse\Administrator\Service\Actor\ActorResolverServiceInterface;
use NX\Component\Fediverse\Administrator\Service\C2S\OAuthService;
use NX\Component\Fediverse\Administrator\Service\Federation\DeliveryServiceInterface;
use RuntimeException;
use Throwable;

/**
 * OutboxController Class
 *
 * Handle Outbox requests.
 *
 * @since  __DEPLOY_VERSION__
 */

final class OutboxController extends BaseController
{
    /**
     * Handle an outbox GET request.
     *
     * Return an OrderedCollection response for the requested actor outbox.
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
            $this->sendJson(['error' => 'Missing handle'], 400);

            return;
        }

        try {
            $container = Factory::getContainer();
            $db        = $container->get(DatabaseDriver::class);

            $actorsModel = new ActorsModel($db);
            $actor       = $actorsModel->getLocalByHandle($handle);

            if ($actor === null) {
                $actor = $this->provisionActorOnDemand($handle);
            }

            if ($actor === null || !$actor->isLocal() || !$actor->isEnabled) {
                $this->sendJson(['error' => 'Actor not found'], 404);

                return;
            }

            $outboxUrl = $this->resolveOutboxUrl($actor);
            if ($outboxUrl === '') {
                $this->sendJson(['error' => 'Outbox unavailable'], 500);

                return;
            }

            $outboxModel = new OutboxModel($db);
            $total       = $outboxModel->countForActor((int) $actor->id);

            $pageParam = $this->input->get('page', null, 'STRING');
            $page      = $this->normalizePageParam($pageParam);

            $contentType = $this->negotiateContentType((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

            if ($page === null) {
                $payload = [
                    '@context'   => 'https://www.w3.org/ns/activitystreams',
                    'id'         => $outboxUrl,
                    'type'       => 'OrderedCollection',
                    'totalItems' => $total,
                ];

                $payload['first'] = $outboxUrl . '?page=1';
                if ($total > 0) {
                    $lastPage = (int) ceil($total / self::OUTBOX_PAGE_SIZE);
                    if ($lastPage > 1) {
                        $payload['last'] = $outboxUrl . '?page=' . $lastPage;
                    }
                }

                $this->sendJson($payload, 200, $contentType);

                return;
            }

            if ($page <= 0) {
                $this->sendJson(['error' => 'Invalid page'], 400);

                return;
            }

            $offset = ($page - 1) * self::OUTBOX_PAGE_SIZE;
            $items  = $outboxModel->getPayloadsForActor((int) $actor->id, self::OUTBOX_PAGE_SIZE, $offset);

            $payload = [
                '@context'     => 'https://www.w3.org/ns/activitystreams',
                'id'           => $outboxUrl . '?page=' . $page,
                'type'         => 'OrderedCollectionPage',
                'partOf'       => $outboxUrl,
                'orderedItems' => $items,
            ];

            if ($page > 1) {
                $payload['prev'] = $outboxUrl . '?page=' . ($page - 1);
            }

            if ($offset + self::OUTBOX_PAGE_SIZE < $total) {
                $payload['next'] = $outboxUrl . '?page=' . ($page + 1);
            }

            $this->sendJson($payload, 200, $contentType);
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];
            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500);
        }
    }

    /**
     * Handle an outbox POST request.
     *
     * Return a placeholder response for posting to an actor outbox.
     *
     * @params string $handle Actor handle.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function post(string $handle): void
    {
        $handle = trim($handle);
        if ($handle === '') {
            $this->sendJson(['error' => 'Missing handle'], 400);

            return;
        }

        try {
            $container = Factory::getContainer();
            $db        = $container->get(DatabaseDriver::class);
            $ctx       = $container->get(RequestContext::class);
            $oauth     = $container->get(OAuthService::class);
            $resolver  = $container->get(ActorResolverServiceInterface::class);
            $delivery  = $container->get(DeliveryServiceInterface::class);

            $token  = $this->extractBearerToken($ctx->getHeader('Authorization'));
            $record = $oauth->requireTokenForActor($token, $handle, 'write');
            $userId = (int) ($record->user_id ?? 0);
            if ($userId <= 0) {
                throw new OAuthException('invalid_token', 'Token has no user', 401);
            }

            $actor = $resolver->ensureLocalActorForUserId($userId);
            if ($actor->id === null) {
                $this->sendJson(['error' => 'Actor not available'], 500);

                return;
            }

            if (!$actor->isEnabled) {
                $this->sendJson(['error' => 'Actor disabled'], 403);

                return;
            }

            $raw = $ctx->getRawBody();
            if (trim($raw) === '') {
                $this->sendJson(['error' => 'Missing request body'], 400);

                return;
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $this->sendJson(['error' => 'Invalid JSON'], 400);

                return;
            }

            $actorMapper = new JoomlaUserActorMapper();

            $payload  = $this->normalizeActivityPayload($payload, $actor, $actorMapper);
            $activity = ActivityEnvelope::fromJson($payload);

            $outboxModel    = new OutboxModel($db);
            $followersModel = new FollowersModel($db);
            $outboxId       = $outboxModel->insertEnvelope((int) $actor->id, $activity);

            $targets = $followersModel->getAcceptedFollowerInboxUrls((int) $actor->id);
            if ($targets !== []) {
                $delivery->enqueue($outboxId, $targets);
            }

            $this->sendJson(['status' => 'accepted', 'id' => $outboxId], 202);
        } catch (OAuthException $e) {
            $payload = ['error' => $e->getError()];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, $e->getStatusCode());
        } catch (RuntimeException $e) {
            $payload = ['error' => 'invalid_request'];
            if (JDEBUG) {
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 400);
        } catch (Throwable $e) {
            $payload = ['error' => 'Internal error'];
            if (JDEBUG) {
                $payload['exception'] = $e::class;
                $payload['message'] = $e->getMessage();
            }

            $this->sendJson($payload, 500);
        }
    }

    /**
     * Normalize an outbound activity payload.
     *
     * Ensure required fields are set and consistent with the local actor.
     *
     * @params array<string,mixed> $payload Activity payload.
     * @params Actor $actor Local actor instance.
     * @params JoomlaUserActorMapper $actorMapper Actor mapper.
     * @return  array<string,mixed> Normalized payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeActivityPayload(
        array $payload,
        Actor $actor,
        JoomlaUserActorMapper $actorMapper,
    ): array {
        $actorUri = trim($actor->uri);
        if ($actorUri === '') {
            throw new RuntimeException('Actor URI missing');
        }

        $incomingActor = $payload['actor'] ?? null;
        if (is_array($incomingActor) && isset($incomingActor['id']) && is_string($incomingActor['id'])) {
            $incomingActor = $incomingActor['id'];
        }

        if (!is_string($incomingActor) || trim($incomingActor) === '') {
            $payload['actor'] = $actorUri;
        } elseif (strcasecmp($incomingActor, $actorUri) !== 0) {
            throw new RuntimeException('Actor mismatch');
        } else {
            $payload['actor'] = $actorUri;
        }

        $type = $payload['type'] ?? null;
        if (!is_string($type) || trim($type) === '') {
            throw new RuntimeException('Missing activity type');
        }

        $id = $payload['id'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            $token   = bin2hex(random_bytes(16));
            $baseUrl = self::resolveBaseUrlForActor($actor, '');
            $payload['id'] = $actorMapper->activityUri($baseUrl, $token);
        }

        if (!isset($payload['@context'])) {
            $payload['@context'] = 'https://www.w3.org/ns/activitystreams';
        }

        return $payload;
    }

    /**
     * Normalize the outbox page query parameter.
     *
     * Accept numeric pages or the boolean "true" to indicate the first page.
     *
     * @params mixed $pageParam Query parameter value.
     *
     * @return  ?int  Page number or null when omitted.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizePageParam(mixed $pageParam): ?int
    {
        if ($pageParam === null || $pageParam === '') {
            return null;
        }

        if ($pageParam === 'true') {
            return 1;
        }

        if (is_numeric($pageParam)) {
            return (int) $pageParam;
        }

        return 0;
    }

    /**
     * Resolve an outbox URL for a local actor.
     *
     * Fallback to the actor URI when the stored outbox URL is missing.
     *
     * @params Actor $actor Local actor instance.
     *
     * @return  string  Outbox URL.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveOutboxUrl(Actor $actor): string
    {
        $outboxUrl = trim((string) ($actor->outboxUrl ?? ''));
        if ($outboxUrl !== '') {
            return $outboxUrl;
        }

        $uri = trim((string) ($actor->uri ?? ''));
        if ($uri === '') {
            return '';
        }

        return rtrim($uri, '/') . '/outbox';
    }

    /**
     * Negotiate the outbox response content type.
     *
     * Select the appropriate content type based on the Accept header.
     *
     * @params string $acceptHeader Accept header value.
     *
     * @return  string  Selected content type.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function negotiateContentType(string $acceptHeader): string
    {
        $accept = strtolower($acceptHeader);
        if (str_contains($accept, 'application/ld+json')) {
            return 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        }

        return 'application/activity+json';
    }

    /**
     * Resolve base URL for activity IDs.
     *
     * Prefer the actor URI origin to keep ids stable.
     *
     * @params Actor $actor Local actor instance.
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

        try {
            $resolver = Factory::getContainer()->get(ActorResolverServiceInterface::class);
        } catch (Throwable) {
            return null;
        }

        try {
            return $resolver->ensureLocalActorForUserId((int) $m[1]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extract a Bearer token from an Authorization header.
     *
     * @params ?string $header Authorization header value.
     *
     * @return  string  Access token or empty string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractBearerToken(?string $header): string
    {
        $header = $header !== null ? trim($header) : '';
        if ($header === '') {
            return '';
        }

        if (preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Send a JSON response.
     *
     * Encode a payload and emit a JSON response with headers.
     *
     * @params array<string,mixed> $payload Response payload.
     * @params int $status HTTP status code.
     *
     * @return  void  None.
     * @throws  \JsonException  if JSON encoding fails.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sendJson(array $payload, int $status, string $contentType = 'application/json'): void
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

    private const OUTBOX_PAGE_SIZE = 20;
}
